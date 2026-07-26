#!/usr/bin/env python3
"""Detect subject focus points for FreeKliping smart crop.

The script intentionally stays outside Laravel dependencies. Configure it with:

FREEKLIPING_CROP_MODE=smart
FREEKLIPING_SMART_CROP_DETECTOR_BINARY=/absolute/path/to/app/Support/Clips/smart_crop_detect.py
FREEKLIPING_SMART_CROP_MODEL=/absolute/path/to/yolo.onnx

It outputs JSON shaped as:
{"points": [{"time": 0.0, "x": 0.5, "y": 0.4, "confidence": 0.91}]}
"""

from __future__ import annotations

import argparse
import json
import math
import os
import sys
import time
from dataclasses import dataclass
from typing import Iterable


PERSON_CLASS_ID = 0


@dataclass(frozen=True)
class Detection:
    class_id: int
    confidence: float
    x1: float
    y1: float
    x2: float
    y2: float


@dataclass(frozen=True)
class Letterbox:
    scale: float
    pad_x: float
    pad_y: float
    width: int
    height: int


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Detect YOLO ONNX subject focus points for smart crop.",
    )
    parser.add_argument("--input", required=True, help="Local source video path.")
    parser.add_argument("--model", required=True, help="YOLO ONNX model path.")
    parser.add_argument("--output", required=True, help="JSON output path.")
    parser.add_argument("--start", type=float, default=0.0, help="Local start offset in seconds.")
    parser.add_argument("--duration", type=float, default=30.0, help="Clip duration in seconds.")
    parser.add_argument("--aspect-ratio", default="9:16", help="Target crop aspect ratio.")
    parser.add_argument("--image-size", type=int, default=640, help="Square YOLO input size.")
    parser.add_argument("--sample-interval", type=float, default=1.0, help="Seconds between sampled frames.")
    parser.add_argument("--confidence", type=float, default=0.35, help="Detection confidence threshold.")
    parser.add_argument("--nms", type=float, default=0.45, help="NMS IoU threshold.")
    parser.add_argument(
        "--class-ids",
        default=str(PERSON_CLASS_ID),
        help='Comma-separated class IDs to keep, or "any". COCO person is 0.',
    )

    return parser.parse_args()


def main() -> int:
    args = parse_args()
    started_at = time.perf_counter()

    if args.duration <= 0:
        write_output(args.output, [], args, started_at, warning="Duration must be greater than zero.")

        return 0

    if not os.path.exists(args.input):
        print(f"Input video does not exist: {args.input}", file=sys.stderr)

        return 2

    if not os.path.exists(args.model):
        print(f"ONNX model does not exist: {args.model}", file=sys.stderr)

        return 2

    try:
        cv2, np = import_dependencies()
    except ImportError as exception:
        print(
            "Missing Python dependencies. Install opencv-python and numpy for smart crop detection.",
            file=sys.stderr,
        )
        print(str(exception), file=sys.stderr)

        return 2

    net = cv2.dnn.readNetFromONNX(args.model)
    class_ids = selected_class_ids(args.class_ids)
    cap = cv2.VideoCapture(args.input)

    if not cap.isOpened():
        print(f"Unable to open video: {args.input}", file=sys.stderr)

        return 2

    try:
        fps = cap.get(cv2.CAP_PROP_FPS)
        fps = fps if fps and fps > 0 else 30.0
        points = []

        for sample_time in sample_times(args.duration, args.sample_interval):
            cap.set(cv2.CAP_PROP_POS_MSEC, max(0.0, args.start + sample_time) * 1000)
            ok, frame = cap.read()

            if not ok or frame is None:
                continue

            detections = detect_frame(
                cv2=cv2,
                np=np,
                net=net,
                frame=frame,
                image_size=args.image_size,
                confidence_threshold=args.confidence,
                nms_threshold=args.nms,
                class_ids=class_ids,
            )

            if detections:
                points.append(focus_point(sample_time, detections[0], frame.shape[1], frame.shape[0]))
    finally:
        cap.release()

    write_output(args.output, points, args, started_at)

    return 0


def import_dependencies():
    import cv2  # type: ignore
    import numpy as np  # type: ignore

    return cv2, np


def selected_class_ids(value: str) -> set[int] | None:
    if value.strip().lower() == "any":
        return None

    ids = set()

    for raw_id in value.split(","):
        raw_id = raw_id.strip()

        if raw_id:
            ids.add(int(raw_id))

    return ids or {PERSON_CLASS_ID}


def sample_times(duration: float, interval: float) -> Iterable[float]:
    interval = max(0.2, interval)
    count = max(1, int(math.ceil(duration / interval)))

    for index in range(count):
        yield min(duration, index * interval)

    if count == 1 or (count - 1) * interval < duration:
        yield duration


def detect_frame(
    *,
    cv2,
    np,
    net,
    frame,
    image_size: int,
    confidence_threshold: float,
    nms_threshold: float,
    class_ids: set[int] | None,
) -> list[Detection]:
    blob, letterbox = make_blob(cv2, np, frame, image_size)
    net.setInput(blob)
    outputs = net.forward()
    detections = parse_yolo_output(np, outputs, letterbox, confidence_threshold, class_ids)

    if not detections:
        return []

    boxes = [
        [
            int(max(0, detection.x1)),
            int(max(0, detection.y1)),
            int(max(1, detection.x2 - detection.x1)),
            int(max(1, detection.y2 - detection.y1)),
        ]
        for detection in detections
    ]
    scores = [float(detection.confidence) for detection in detections]
    kept = cv2.dnn.NMSBoxes(boxes, scores, confidence_threshold, nms_threshold)
    kept_indices = flatten_indices(kept)

    return sorted((detections[index] for index in kept_indices), key=lambda detection: detection.confidence, reverse=True)


def make_blob(cv2, np, frame, image_size: int):
    original_height, original_width = frame.shape[:2]
    scale = min(image_size / original_width, image_size / original_height)
    resized_width = int(round(original_width * scale))
    resized_height = int(round(original_height * scale))
    resized = cv2.resize(frame, (resized_width, resized_height), interpolation=cv2.INTER_LINEAR)
    canvas = np.full((image_size, image_size, 3), 114, dtype=frame.dtype)
    pad_x = (image_size - resized_width) // 2
    pad_y = (image_size - resized_height) // 2
    canvas[pad_y : pad_y + resized_height, pad_x : pad_x + resized_width] = resized
    blob = cv2.dnn.blobFromImage(canvas, scalefactor=1 / 255.0, size=(image_size, image_size), swapRB=True, crop=False)

    return blob, Letterbox(scale=scale, pad_x=pad_x, pad_y=pad_y, width=original_width, height=original_height)


def parse_yolo_output(np, outputs, letterbox: Letterbox, confidence_threshold: float, class_ids: set[int] | None) -> list[Detection]:
    if isinstance(outputs, (list, tuple)):
        if not outputs:
            return []

        outputs = outputs[0]

    predictions = np.squeeze(outputs)

    if predictions.ndim == 1:
        predictions = np.expand_dims(predictions, axis=0)

    if predictions.ndim != 2:
        return []

    if predictions.shape[0] in (6, 84, 85) and predictions.shape[1] > predictions.shape[0]:
        predictions = predictions.transpose()

    if predictions.shape[1] == 6:
        return parse_end_to_end_predictions(predictions, letterbox, confidence_threshold, class_ids)

    if predictions.shape[1] < 6:
        return []

    return parse_raw_predictions(np, predictions, letterbox, confidence_threshold, class_ids)


def parse_end_to_end_predictions(predictions, letterbox: Letterbox, confidence_threshold: float, class_ids: set[int] | None) -> list[Detection]:
    detections = []

    for row in predictions:
        confidence = float(row[4])
        class_id = int(row[5])

        if confidence < confidence_threshold:
            continue

        if class_ids is not None and class_id not in class_ids:
            continue

        x1, y1 = undo_letterbox(float(row[0]), float(row[1]), letterbox)
        x2, y2 = undo_letterbox(float(row[2]), float(row[3]), letterbox)
        detections.append(clamp_detection(class_id, confidence, x1, y1, x2, y2, letterbox))

    return detections


def parse_raw_predictions(np, predictions, letterbox: Letterbox, confidence_threshold: float, class_ids: set[int] | None) -> list[Detection]:
    detections = []
    class_scores_start = 5 if predictions.shape[1] > 84 else 4

    for row in predictions:
        class_scores = row[class_scores_start:]

        if class_scores.size == 0:
            continue

        class_id = int(np.argmax(class_scores))

        if class_ids is not None and class_id not in class_ids:
            continue

        class_confidence = float(class_scores[class_id])
        objectness = float(row[4]) if class_scores_start == 5 else 1.0
        confidence = objectness * class_confidence

        if confidence < confidence_threshold:
            continue

        cx, cy, width, height = [float(value) for value in row[:4]]
        x1, y1 = undo_letterbox(cx - width / 2, cy - height / 2, letterbox)
        x2, y2 = undo_letterbox(cx + width / 2, cy + height / 2, letterbox)
        detections.append(clamp_detection(class_id, confidence, x1, y1, x2, y2, letterbox))

    return detections


def undo_letterbox(x: float, y: float, letterbox: Letterbox) -> tuple[float, float]:
    return (x - letterbox.pad_x) / letterbox.scale, (y - letterbox.pad_y) / letterbox.scale


def clamp_detection(class_id: int, confidence: float, x1: float, y1: float, x2: float, y2: float, letterbox: Letterbox) -> Detection:
    x1 = max(0.0, min(float(letterbox.width), x1))
    y1 = max(0.0, min(float(letterbox.height), y1))
    x2 = max(0.0, min(float(letterbox.width), x2))
    y2 = max(0.0, min(float(letterbox.height), y2))

    return Detection(
        class_id=class_id,
        confidence=confidence,
        x1=min(x1, x2),
        y1=min(y1, y2),
        x2=max(x1, x2),
        y2=max(y1, y2),
    )


def flatten_indices(indices) -> list[int]:
    if indices is None:
        return []

    if hasattr(indices, "flatten"):
        return [int(index) for index in indices.flatten()]

    return [int(index[0] if isinstance(index, (list, tuple)) else index) for index in indices]


def focus_point(time_seconds: float, detection: Detection, frame_width: int, frame_height: int) -> dict[str, float]:
    box_width = max(1.0, detection.x2 - detection.x1)
    box_height = max(1.0, detection.y2 - detection.y1)
    x = (detection.x1 + box_width / 2) / max(1, frame_width)
    y = (detection.y1 + box_height * 0.42) / max(1, frame_height)

    return {
        "time": round(float(time_seconds), 3),
        "x": round(max(0.0, min(1.0, x)), 4),
        "y": round(max(0.0, min(1.0, y)), 4),
        "confidence": round(float(detection.confidence), 4),
    }


def write_output(output_path: str, points: list[dict[str, float]], args: argparse.Namespace, started_at: float, warning: str | None = None) -> None:
    payload = {
        "points": points,
        "meta": {
            "detector": "opencv-dnn-yolo-onnx",
            "aspectRatio": args.aspect_ratio,
            "duration": args.duration,
            "sampleInterval": args.sample_interval,
            "pointCount": len(points),
            "elapsedMs": int(round((time.perf_counter() - started_at) * 1000)),
        },
    }

    if warning is not None:
        payload["warning"] = warning

    os.makedirs(os.path.dirname(output_path), exist_ok=True)

    with open(output_path, "w", encoding="utf-8") as handle:
        json.dump(payload, handle, separators=(",", ":"))


if __name__ == "__main__":
    raise SystemExit(main())
