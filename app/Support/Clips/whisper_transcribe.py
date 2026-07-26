#!/usr/bin/env python3
"""Transcribe audio/video with faster-whisper for FreeKliping.

Outputs:
- JSON: detailed segments + words
- JSON3: YouTube-like transcript events for clip recommendation
- SRT: subtitle file for future burn-in fallback
"""

from __future__ import annotations

import argparse
import html
import json
import os
import sys
import time
from dataclasses import asdict, dataclass
from typing import Iterable


@dataclass(frozen=True)
class TranscriptWord:
    start: float
    end: float
    text: str
    probability: float | None


@dataclass(frozen=True)
class TranscriptSegment:
    start: float
    end: float
    text: str
    words: list[TranscriptWord]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Transcribe media using faster-whisper.")
    parser.add_argument("--input", required=True, help="Audio or video file path.")
    parser.add_argument("--output-json", required=True, help="Detailed JSON output path.")
    parser.add_argument("--output-json3", required=True, help="YouTube-style JSON3 output path.")
    parser.add_argument("--output-srt", required=True, help="SRT output path.")
    parser.add_argument("--model", default="small", help="faster-whisper model name or local path.")
    parser.add_argument("--language", default="id", help="Language code, e.g. id or en.")
    parser.add_argument("--device", default="cpu", help="cpu or cuda.")
    parser.add_argument("--compute-type", default="int8", help="int8, float16, float32, int8_float16, etc.")
    parser.add_argument("--beam-size", type=int, default=5, help="Beam size for decoding.")
    parser.add_argument("--vad-filter", action=argparse.BooleanOptionalAction, default=True, help="Enable Silero VAD.")

    return parser.parse_args()


def main() -> int:
    args = parse_args()
    started_at = time.perf_counter()

    if not os.path.exists(args.input):
        print(f"Input media does not exist: {args.input}", file=sys.stderr)

        return 2

    try:
        from faster_whisper import WhisperModel
    except ImportError as exception:
        print("Missing Python dependency. Install faster-whisper first.", file=sys.stderr)
        print(str(exception), file=sys.stderr)

        return 2

    model = WhisperModel(args.model, device=args.device, compute_type=args.compute_type)
    segments_iterable, info = model.transcribe(
        args.input,
        language=args.language or None,
        beam_size=max(1, args.beam_size),
        word_timestamps=True,
        vad_filter=args.vad_filter,
    )
    segments = normalize_segments(segments_iterable)
    language = getattr(info, "language", None) or args.language

    write_json(args.output_json, segments, language, args, started_at)
    write_json3(args.output_json3, segments)
    write_srt(args.output_srt, segments)

    return 0


def normalize_segments(raw_segments: Iterable[object]) -> list[TranscriptSegment]:
    segments = []

    for segment in raw_segments:
        words = []

        for word in getattr(segment, "words", None) or []:
            text = str(getattr(word, "word", "")).strip()

            if text == "":
                continue

            words.append(
                TranscriptWord(
                    start=float(getattr(word, "start", getattr(segment, "start", 0.0))),
                    end=float(getattr(word, "end", getattr(segment, "end", 0.0))),
                    text=text,
                    probability=word_probability(word),
                )
            )

        text = str(getattr(segment, "text", "")).strip()

        if text == "" and words:
            text = " ".join(word.text for word in words)

        if text == "":
            continue

        segments.append(
            TranscriptSegment(
                start=float(getattr(segment, "start", 0.0)),
                end=float(getattr(segment, "end", 0.0)),
                text=text,
                words=words,
            )
        )

    return segments


def word_probability(word: object) -> float | None:
    probability = getattr(word, "probability", None)

    if probability is None:
        return None

    return round(float(probability), 4)


def write_json(path: str, segments: list[TranscriptSegment], language: str, args: argparse.Namespace, started_at: float) -> None:
    payload = {
        "language": language,
        "model": args.model,
        "device": args.device,
        "computeType": args.compute_type,
        "elapsedMs": int(round((time.perf_counter() - started_at) * 1000)),
        "segments": [
            {
                "start": segment.start,
                "end": segment.end,
                "text": segment.text,
                "words": [asdict(word) for word in segment.words],
            }
            for segment in segments
        ],
    }

    write_text(path, json.dumps(payload, ensure_ascii=False, separators=(",", ":")))


def write_json3(path: str, segments: list[TranscriptSegment]) -> None:
    payload = {
        "events": [
            {
                "tStartMs": milliseconds(segment.start),
                "dDurationMs": max(1, milliseconds(segment.end - segment.start)),
                "segs": json3_segments(segment),
            }
            for segment in segments
        ]
    }

    write_text(path, json.dumps(payload, ensure_ascii=False, separators=(",", ":")))


def json3_segments(segment: TranscriptSegment) -> list[dict[str, object]]:
    if not segment.words:
        return [{"utf8": segment.text}]

    return [
        {
            "utf8": (" " if index > 0 else "") + word.text,
            "tOffsetMs": max(0, milliseconds(word.start - segment.start)),
        }
        for index, word in enumerate(segment.words)
    ]


def write_srt(path: str, segments: list[TranscriptSegment]) -> None:
    blocks = []

    for index, segment in enumerate(segments, start=1):
        blocks.append(
            "\n".join(
                [
                    str(index),
                    f"{srt_timestamp(segment.start)} --> {srt_timestamp(segment.end)}",
                    html.unescape(segment.text),
                ]
            )
        )

    write_text(path, "\n\n".join(blocks) + ("\n" if blocks else ""))


def milliseconds(seconds: float) -> int:
    return int(round(max(0.0, seconds) * 1000))


def srt_timestamp(seconds: float) -> str:
    total_ms = milliseconds(seconds)
    hours = total_ms // 3_600_000
    total_ms %= 3_600_000
    minutes = total_ms // 60_000
    total_ms %= 60_000
    secs = total_ms // 1000
    ms = total_ms % 1000

    return f"{hours:02d}:{minutes:02d}:{secs:02d},{ms:03d}"


def write_text(path: str, content: str) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)

    with open(path, "w", encoding="utf-8") as handle:
        handle.write(content)


if __name__ == "__main__":
    raise SystemExit(main())
