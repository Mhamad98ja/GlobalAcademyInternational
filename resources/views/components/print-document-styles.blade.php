<style>
    .print-document {
        --document-navy: #123053;
        --document-teal: #0f766e;
        --document-ink: #16243a;
        --document-muted: #64748b;
        --document-rule: #cbd5e1;
        --document-pale: #f8fafc;
        --document-green: #e7f5ef;
        --document-green-ink: #166534;
        box-sizing: border-box;
        width: min(100%, 210mm);
        min-height: 297mm;
        margin: 0 auto;
        overflow: hidden;
        background: #fff;
        color: var(--document-ink);
        box-shadow: 0 18px 48px rgb(15 23 42 / 14%);
        font-size: 0.875rem;
        line-height: 1.45;
    }

    .print-document *, .print-document *::before, .print-document *::after { box-sizing: border-box; }
    .print-masthead { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; border-bottom: 5px solid var(--document-teal); background: var(--document-navy); color: #fff; padding: 1.55rem 2rem; }
    .print-brand { display: flex; min-width: 0; align-items: center; gap: 0.95rem; }
    .print-school-mark { width: 2.8rem; flex: 0 0 auto; color: #fff; }
    .print-school-name { font-size: 1.3rem; font-weight: 800; letter-spacing: 0.025em; line-height: 1.1; }
    .print-school-contact { margin-top: 0.28rem; color: rgb(255 255 255 / 75%); font-size: 0.72rem; }
    .print-document-kind { flex: 0 0 auto; border-left: 1px solid rgb(255 255 255 / 35%); padding-left: 1.5rem; color: rgb(255 255 255 / 85%); font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-align: right; text-transform: uppercase; }
    .print-document-body { padding: 1.75rem 2rem 1.25rem; }
    .print-title-row { display: flex; align-items: start; justify-content: space-between; gap: 1.5rem; margin-bottom: 1.5rem; }
    .print-title { margin: 0; color: var(--document-navy); font-size: 1.45rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.15; }
    .print-title-note { margin-top: 0.3rem; color: var(--document-muted); font-size: 0.76rem; }
    .print-reference { min-width: 11rem; border: 1px solid var(--document-rule); padding: 0.75rem 0.9rem; }
    .print-reference-row { display: flex; justify-content: space-between; gap: 1rem; color: var(--document-muted); font-size: 0.72rem; }
    .print-reference-row + .print-reference-row { margin-top: 0.35rem; }
    .print-reference-row strong { color: var(--document-ink); font-weight: 750; text-align: right; }
    .print-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.75rem; margin: 0 0 1.5rem; border-top: 1px solid var(--document-rule); border-bottom: 1px solid var(--document-rule); padding: 1rem 0; }
    .print-info-label { color: var(--document-teal); font-size: 0.66rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
    .print-info-value { margin-top: 0.25rem; color: var(--document-ink); font-size: 0.85rem; font-weight: 700; }
    .print-info-detail { margin-top: 0.1rem; color: var(--document-muted); font-size: 0.73rem; }
    .print-table { width: 100%; border-collapse: collapse; }
    .print-table thead th { background: var(--document-navy); border: 1px solid #274362; color: #fff; font-size: 0.67rem; font-weight: 800; letter-spacing: 0.035em; padding: 0.65rem 0.7rem; text-align: left; text-transform: uppercase; }
    .print-table tbody td { border: 1px solid var(--document-rule); color: #334155; padding: 0.62rem 0.7rem; vertical-align: top; }
    .print-table .print-number { text-align: right; white-space: nowrap; }
    .print-table .print-quantity { text-align: center; }
    .print-financial-summary { display: grid; grid-template-columns: minmax(0, 1fr) 13rem; gap: 1.5rem; margin-top: 1.25rem; }
    .print-notes { border: 1px solid var(--document-rule); min-height: 5rem; padding: 0.85rem 1rem; }
    .print-notes-title { color: var(--document-navy); font-size: 0.68rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
    .print-notes p { margin: 0.4rem 0 0; color: var(--document-muted); font-size: 0.75rem; white-space: pre-line; }
    .print-total-list { border: 1px solid var(--document-rule); }
    .print-total-row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.54rem 0.75rem; color: var(--document-muted); font-size: 0.77rem; }
    .print-total-row strong { color: var(--document-ink); }
    .print-total-row--emphasis { border-top: 1px solid var(--document-rule); background: var(--document-green); color: var(--document-green-ink); font-size: 0.87rem; font-weight: 800; }
    .print-total-row--emphasis strong { color: var(--document-green-ink); }
    .print-filter-summary { margin: -0.35rem 0 1rem; color: var(--document-muted); font-size: 0.72rem; }
    .print-filter-summary strong { color: var(--document-navy); }
    .print-filter-summary span + span::before { color: #94a3b8; content: " • "; padding: 0 0.15rem; }
    .print-document .table-wrap { overflow: visible; }
    .print-document .data-table { width: 100%; min-width: 0; border-collapse: collapse; }
    .print-document .data-table th { background: var(--document-navy); border: 1px solid #274362; color: #fff; font-size: 0.62rem; font-weight: 800; letter-spacing: 0.025em; padding: 0.58rem 0.55rem; text-align: left; text-transform: uppercase; }
    .print-document .data-table td { border: 1px solid var(--document-rule); color: #334155; padding: 0.52rem 0.55rem; white-space: normal; }
    .print-document .empty { border: 1px solid var(--document-rule); color: var(--document-muted); padding: 2rem; }
    .print-signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-top: 2.2rem; }
    .print-signature { border-top: 1px solid #64748b; color: var(--document-muted); font-size: 0.65rem; font-weight: 700; padding-top: 0.42rem; text-align: center; text-transform: uppercase; }
    .print-document-footer { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.8rem; border-top: 2px solid var(--document-teal); color: var(--document-muted); font-size: 0.66rem; padding-top: 0.65rem; }

    @media print {
        @page { size: A4 portrait; margin: 10mm; }
        html, body { background: #fff !important; }
        body { font-size: 10pt; }
        .page { max-width: none !important; padding: 0 !important; }
        .print-document { box-shadow: none !important; min-height: 0; width: 100%; }
        .print-document-body { padding: 12mm 12mm 8mm; }
        .print-masthead { padding: 10mm 12mm; }
        .print-table thead { display: table-header-group; }
        .print-table tr, .print-document .data-table tr { break-inside: avoid; page-break-inside: avoid; }
        .print-document .data-table th { font-size: 0.56rem; padding: 0.42rem; }
        .print-document .data-table td { font-size: 0.62rem; padding: 0.42rem; }
        .print-document-footer { margin-top: 1.1rem; }
    }

    @media screen and (max-width: 640px) {
        .print-document { min-height: 0; }
        .print-masthead, .print-title-row, .print-document-footer { align-items: flex-start; flex-direction: column; }
        .print-document-kind { border-left: 0; border-top: 1px solid rgb(255 255 255 / 35%); padding-left: 0; padding-top: 0.65rem; text-align: left; }
        .print-document-body { padding: 1.25rem; }
        .print-info-grid, .print-financial-summary, .print-signatures { grid-template-columns: 1fr; }
        .print-reference { width: 100%; }
    }
</style>
