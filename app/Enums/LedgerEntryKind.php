<?php

namespace App\Enums;

enum LedgerEntryKind: string
{
    case Proposition = 'proposition';
    case Supersession = 'supersession';
    case Attestation = 'attestation';
    case Receipt = 'receipt';
    case ReceiptContext = 'receipt_context';
}
