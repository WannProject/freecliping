export type ClipWorkspaceTab = 'recommended' | 'manual';

export type SupportTransparency = {
    caption: string;
    currency: string;
    monthlyTarget: number;
    totalSupported: number;
    progressPercent: number;
    costItems: {
        label: string;
        amount: number;
    }[];
    supporters: {
        name: string;
        amount: number;
    }[];
};

export const defaultSupportTransparency: SupportTransparency = {
    caption: 'Bantu bayar sewa server agar FreeKliping tetap aktif.',
    currency: 'IDR',
    monthlyTarget: 500000,
    totalSupported: 0,
    progressPercent: 0,
    costItems: [],
    supporters: [],
};
