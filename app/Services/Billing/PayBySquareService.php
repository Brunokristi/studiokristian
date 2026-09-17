<?php

namespace App\Services\Billing;

use App\Models\ProjectInvoice;
use PayBySquare\PayBySquare;
use RuntimeException;

class PayBySquareService
{
    /**
     * Create a PAY by square instance for an invoice.
     */
    public function make(
        ProjectInvoice $invoice
    ): PayBySquare {
        $iban = $this->normalizeIban(
            $invoice->supplier_iban
        );

        if (! $iban) {
            throw new RuntimeException(
                'The invoice does not have a supplier IBAN.'
            );
        }

        if ((int) $invoice->total <= 0) {
            throw new RuntimeException(
                'The invoice total must be greater than zero.'
            );
        }

        $supplier = config(
            'billing.supplier',
            []
        );

        $beneficiaryName = trim(
            (string) (
                $invoice->supplier_name
                ?: ($supplier['name'] ?? '')
            )
        );

        $attributes = [
            'amount' => $this->amount(
                $invoice
            ),

            'currencyCode' => strtoupper(
                $invoice->currency ?: 'EUR'
            ),

            'iban' => $iban,

            'paymentDueDate' => $this->dueDate(
                $invoice
            ),

            'paymentNote' => $this->paymentNote(
                $invoice
            ),
        ];

        if ($beneficiaryName !== '') {
            $attributes['beneficiaryName'] =
                $beneficiaryName;
        }

        $variableSymbol = $this->variableSymbol(
            $invoice
        );

        if ($variableSymbol) {
            $attributes['variableSymbol'] =
                $variableSymbol;
        }

        $swift = $this->normalizeSwift(
            $supplier['swift'] ?? null
        );

        if ($swift) {
            $attributes['bic'] =
                $swift;
        }

        return new PayBySquare(
            $attributes
        );
    }

    /**
     * Return the encoded PAY by square payload.
     */
    public function payload(
        ProjectInvoice $invoice
    ): string {
        return $this
            ->make(
                $invoice
            )
            ->getPayBySquareData();
    }

    /**
     * Return the generated QR code as raw PNG data.
     */
    public function png(
        ProjectInvoice $invoice,
        int $size = 400,
        int $margin = 0
    ): string {
        $png = $this
            ->make($invoice)
            ->generateQrCode([
                'size' => $size,
                'margin' => $margin,
            ]);

        return $this->recolorQrCode(
            $png,
            19,
            62,
            180
        );
    }

    /**
     * Return the QR code as a data URI.
     *
     * This can be passed directly into:
     *
     * <img src="{{ $paymentQrDataUri }}">
     */
    public function dataUri(
        ProjectInvoice $invoice,
        int $size = 400,
        int $margin = 0
    ): string {
        return
            'data:image/png;base64,'
            .base64_encode(
                $this->png(
                    $invoice,
                    $size,
                    $margin
                )
            );
    }

    /**
     * Return everything required to display PAY by square
     * on the Vue/client frontend.
     */
    public function frontendData(
        ProjectInvoice $invoice
    ): array {
        $supplier = config(
            'billing.supplier',
            []
        );

        return [
            'qr_code' =>
                $this->dataUri(
                    $invoice
                ),

            'amount' =>
                $this->amount(
                    $invoice
                ),

            'currency' =>
                strtoupper(
                    $invoice->currency
                        ?: 'EUR'
                ),

            'iban' =>
                $this->normalizeIban(
                    $invoice->supplier_iban
                ),

            'swift' =>
                $this->normalizeSwift(
                    $supplier['swift']
                        ?? null
                ),

            'variable_symbol' =>
                $this->variableSymbol(
                    $invoice
                ),

            'due_date' =>
                $this->dateForFrontend(
                    $invoice->due_date
                        ?? $invoice->issue_date
                ),

            'beneficiary' =>
                $invoice->supplier_name
                ?: (
                    $supplier['name']
                    ?? ''
                ),

            'payment_note' =>
                $this->paymentNote(
                    $invoice
                ),
        ];
    }

    /**
     * Invoice amounts are stored in cents.
     */
    private function amount(
        ProjectInvoice $invoice
    ): float {
        return round(
            ((int) $invoice->total) / 100,
            2
        );
    }

    /**
     * PAY by square expects YYYYMMDD.
     *
     * Invoice dates may be Carbon instances or strings,
     * especially for unsaved preview invoices.
     */
    private function dueDate(
        ProjectInvoice $invoice
    ): string {
        $date =
            $invoice->due_date
            ?? $invoice->issue_date
            ?? now();

        if (
            $date instanceof
            \DateTimeInterface
        ) {
            return $date->format(
                'Ymd'
            );
        }

        return \Illuminate\Support\Carbon::parse(
            $date
        )->format(
            'Ymd'
        );
    }

    /**
     * Return a numeric PAY by square variable symbol.
     *
     * Preview invoices use "PREVIEW", so they intentionally
     * return null and PAY by square is generated without a VS.
     */
    private function variableSymbol(
        ProjectInvoice $invoice
    ): ?string {
        $value = (string) (
            $invoice->variable_symbol
            ?: $invoice->invoice_number
        );

        $value = preg_replace(
            '/\D+/',
            '',
            $value
        );

        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return substr(
            $value,
            0,
            10
        );
    }

    /**
     * Payment note displayed in the banking application.
     */
    private function paymentNote(
        ProjectInvoice $invoice
    ): string {
        $number = trim(
            (string) $invoice->invoice_number
        );

        if (
            $number === ''
            || strtoupper($number) === 'PREVIEW'
        ) {
            return 'Faktura';
        }

        return mb_substr(
            'Faktura '.$number,
            0,
            35
        );
    }

    /**
     * Normalize IBAN for PAY by square.
     */
    private function normalizeIban(
        ?string $iban
    ): ?string {
        $iban = strtoupper(
            preg_replace(
                '/\s+/',
                '',
                trim(
                    (string) $iban
                )
            ) ?? ''
        );

        return $iban !== ''
            ? $iban
            : null;
    }

    /**
     * Normalize BIC/SWIFT.
     */
    private function normalizeSwift(
        ?string $swift
    ): ?string {
        $swift = strtoupper(
            preg_replace(
                '/\s+/',
                '',
                trim(
                    (string) $swift
                )
            ) ?? ''
        );

        return $swift !== ''
            ? $swift
            : null;
    }

    /**
     * Normalize invoice date for frontend JSON.
     */
    private function dateForFrontend(
        mixed $date
    ): ?string {
        if (! $date) {
            return null;
        }

        if (
            $date instanceof
            \DateTimeInterface
        ) {
            return $date->format(
                'Y-m-d'
            );
        }

        return \Illuminate\Support\Carbon::parse(
            $date
        )->format(
            'Y-m-d'
        );
    }

    private function recolorQrCode(
        string $png,
        int $red,
        int $green,
        int $blue
    ): string {
        $image = imagecreatefromstring($png);

        if ($image === false) {
            throw new RuntimeException(
                'Unable to read generated PAY by square QR code.'
            );
        }

        imagepalettetotruecolor($image);

        $width = imagesx($image);
        $height = imagesy($image);

        $brandColor = imagecolorallocate(
            $image,
            $red,
            $green,
            $blue
        );

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $colorIndex = imagecolorat(
                    $image,
                    $x,
                    $y
                );

                $color = imagecolorsforindex(
                    $image,
                    $colorIndex
                );

                /*
                * Only replace dark pixels.
                *
                * The generated QR is black on white, so this keeps
                * the white background and quiet zone untouched.
                */
                if (
                    $color['red'] < 128
                    && $color['green'] < 128
                    && $color['blue'] < 128
                ) {
                    imagesetpixel(
                        $image,
                        $x,
                        $y,
                        $brandColor
                    );
                }
            }
        }

        ob_start();

        imagepng(
            $image
        );

        $result = ob_get_clean();

        imagedestroy(
            $image
        );

        if ($result === false) {
            throw new RuntimeException(
                'Unable to encode recolored PAY by square QR code.'
            );
        }

        return $result;
    }
}