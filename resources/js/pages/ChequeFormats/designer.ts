import type { ChequeFieldForm, FieldDefinition } from './types';

const defaultGeometry: Record<string, [number, number, number, number]> = {
    account_payee: [72, 8, 56, 6],
    cheque_date: [155, 16, 38, 7],
    party_name_1: [25, 27, 125, 7],
    party_name_2: [25, 36, 125, 7],
    amount_words_1: [25, 47, 125, 7],
    amount_words_2: [25, 56, 125, 7],
    amount_figures: [155, 47, 38, 8],
    label_1: [25, 68, 65, 7],
    label_2: [100, 68, 65, 7],
    signature: [145, 78, 48, 7],
    company_logo: [85, 65, 25, 18],
};

export const createDefaultFields = (definitions: FieldDefinition[]): ChequeFieldForm[] =>
    definitions.map((definition) => {
        const [x, y, width, height] = defaultGeometry[definition.key] ?? [5, 5, 40, 7];

        return {
            field_key: definition.key,
            x_position_mm: x,
            y_position_mm: y,
            width_mm: width,
            height_mm: height,
            font_family: 'Arial',
            font_size_pt: 10,
            font_weight: 400,
            is_italic: false,
            is_underline: false,
            text_align: 'left',
            is_visible: true,
        };
    });

export const clamp = (value: number, minimum: number, maximum: number): number => Math.min(Math.max(value, minimum), Math.max(minimum, maximum));

export const roundMeasurement = (value: number): number => Math.round(value * 100) / 100;
