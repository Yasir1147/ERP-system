export const overtimeLabel = (value: number | string | null | undefined): string => {
    const minutes = Math.round(Number(value || 0) * 60);
    const hours = Math.floor(minutes / 60);
    return [hours ? `${hours} hr` : '', minutes % 60 ? `${minutes % 60} min` : ''].filter(Boolean).join(' ') || '0 hr';
};
