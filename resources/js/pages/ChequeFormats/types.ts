export interface BankOption {
    id: number;
    name: string;
}

export interface FieldDefinition {
    key: string;
    label: string;
}

export interface ChequeFieldForm {
    field_key: string;
    x_position_mm: number;
    y_position_mm: number;
    width_mm: number | null;
    height_mm: number | null;
    font_family: string;
    font_size_pt: number;
    font_weight: number;
    is_italic: boolean;
    is_underline: boolean;
    text_align: 'left' | 'center' | 'right';
    is_visible: boolean;
}

export interface ChequeFormatFormData {
    bank_id: string;
    name: string;
    cheque_width_mm: number;
    cheque_height_mm: number;
    date_format: string;
    amount_figures_prefix: string;
    amount_figures_suffix: string;
    amount_words_prefix: string;
    amount_words_suffix: string;
    party_name_prefix: string;
    party_name_suffix: string;
    party_name_max_length: number;
    amount_words_max_length: number;
    account_payee_text: string;
    label_1_text: string;
    label_2_text: string;
    signature_text: string;
    version: number | null;
    fields: ChequeFieldForm[];
}

export interface StoredChequeFormat extends ChequeFormatFormData {
    id: number;
    version: number;
    background_image_url: string | null;
    logo_image_url: string | null;
    next_cheque_number: number | null;
}
