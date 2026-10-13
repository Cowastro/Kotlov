<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class CommercialDocumentPdf
{
    public function render(CommercialDocument $document): string
    {
        $document->loadMissing(['items', 'manager', 'sourceDocument']);
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.commercial-document', [
            'document' => $document,
            'seller' => $document->seller_snapshot,
            'logo' => $this->logoDataUri(),
            'amountWords' => $this->amountWords((float) $document->total),
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    public function storeForMail(CommercialDocument $document): array
    {
        $filename = $document->type === 'invoice'
            ? 'Счет-'.$document->number.'.pdf'
            : 'Коммерческое-предложение-'.$document->number.'.pdf';
        $path = 'crm-mail-attachments/commercial-documents/'.$document->id.'/'.$filename;
        Storage::disk('local')->put($path, $this->render($document));

        return ['path' => $path, 'name' => $filename];
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('img/logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    private function amountWords(float $amount): string
    {
        $rubles = (int) floor($amount);
        $kopecks = (int) round(($amount - $rubles) * 100);

        return trim($this->integerWords($rubles)).' '.str_pad((string) $kopecks, 2, '0', STR_PAD_LEFT).' коп.';
    }

    private function integerWords(int $number): string
    {
        if ($number === 0) {
            return 'ноль рублей';
        }

        $ones = ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
        $teens = [10 => 'десять', 11 => 'одиннадцать', 12 => 'двенадцать', 13 => 'тринадцать', 14 => 'четырнадцать', 15 => 'пятнадцать', 16 => 'шестнадцать', 17 => 'семнадцать', 18 => 'восемнадцать', 19 => 'девятнадцать'];
        $tens = [2 => 'двадцать', 3 => 'тридцать', 4 => 'сорок', 5 => 'пятьдесят', 6 => 'шестьдесят', 7 => 'семьдесят', 8 => 'восемьдесят', 9 => 'девяносто'];
        $hundreds = [1 => 'сто', 2 => 'двести', 3 => 'триста', 4 => 'четыреста', 5 => 'пятьсот', 6 => 'шестьсот', 7 => 'семьсот', 8 => 'восемьсот', 9 => 'девятьсот'];

        $chunk = function (int $value, bool $female = false) use ($ones, $teens, $tens, $hundreds): string {
            $parts = [];
            if ($value >= 100) {
                $parts[] = $hundreds[intdiv($value, 100)];
                $value %= 100;
            }
            if ($value >= 10 && $value <= 19) {
                $parts[] = $teens[$value];
            } else {
                if ($value >= 20) {
                    $parts[] = $tens[intdiv($value, 10)];
                    $value %= 10;
                }
                if ($value > 0) {
                    $parts[] = $female && $value === 1 ? 'одна' : ($female && $value === 2 ? 'две' : $ones[$value]);
                }
            }

            return implode(' ', $parts);
        };

        $form = static function (int $value, array $forms): string {
            $lastTwo = $value % 100;
            $last = $value % 10;
            if ($lastTwo >= 11 && $lastTwo <= 19) {
                return $forms[2];
            }

            return $last === 1 ? $forms[0] : (in_array($last, [2, 3, 4], true) ? $forms[1] : $forms[2]);
        };

        $parts = [];
        $millions = intdiv($number, 1_000_000);
        if ($millions > 0) {
            $parts[] = $chunk($millions).' '.$form($millions, ['миллион', 'миллиона', 'миллионов']);
        }
        $thousands = intdiv($number % 1_000_000, 1000);
        if ($thousands > 0) {
            $parts[] = $chunk($thousands, true).' '.$form($thousands, ['тысяча', 'тысячи', 'тысяч']);
        }
        $remainder = $number % 1000;
        if ($remainder > 0) {
            $parts[] = $chunk($remainder);
        }
        $parts[] = $form($number, ['рубль', 'рубля', 'рублей']);

        return implode(' ', $parts);
    }
}
