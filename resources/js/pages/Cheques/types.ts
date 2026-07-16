import type { ChequeFieldForm } from '../ChequeFormats/types';

export interface ChequeFormatOption {
    id: number;
    name: string;
    bankName: string | null;
    backgroundImageUrl: string | null;
    logoImageUrl: string | null;
    nextChequeNumber: number | null;
    chequeWidthMm: number;
    chequeHeightMm: number;
    dateFormat: string;
    amountFiguresPrefix: string | null;
    amountFiguresSuffix: string | null;
    amountWordsPrefix: string | null;
    amountWordsSuffix: string | null;
    partyNamePrefix: string | null;
    partyNameSuffix: string | null;
    partyNameMaxLength: number;
    amountWordsMaxLength: number;
    accountPayeeText: string | null;
    signatureText: string | null;
    label1Text: string | null;
    label2Text: string | null;
    fields: ChequeFieldForm[];
}

export interface ChequePartyOption {
    id: number;
    name: string;
    contactPerson?: string | null;
    email?: string | null;
    mobile?: string | null;
    phone?: string | null;
    fax?: string | null;
    address?: string | null;
    remarks?: string | null;
}
