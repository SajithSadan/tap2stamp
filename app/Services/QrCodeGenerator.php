<?php

namespace App\Services;

use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class QrCodeGenerator
{
    /**
     * No 0/O or 1/I: codes are read off printed stickers by people, so no
     * two characters may look alike. 32^6 ≈ 1 billion combinations keeps
     * them short but unguessable.
     */
    public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const LENGTH = 6;

    /** Keeps one request well inside shared-hosting time/memory limits. */
    public const MAX_PER_BATCH = 1000;

    public function generate(int $quantity, ?string $name = null): QrBatch
    {
        return DB::transaction(function () use ($quantity, $name) {
            $batch = QrBatch::create(['name' => $name]);
            $remaining = $quantity;
            $serial = 0;

            // Draw a set of unique candidates, drop any already in the table,
            // insert the rest and top up until the batch is full. Collisions
            // are rare, so this is almost always a single pass. The unique
            // index on qr_codes.code is the final guarantee.
            while ($remaining > 0) {
                $candidates = [];

                while (count($candidates) < $remaining) {
                    $candidates[$this->randomCode()] = true;
                }

                $codes = array_keys($candidates);
                $taken = QrCode::whereIn('code', $codes)->pluck('code')->all();
                $fresh = array_values(array_diff($codes, $taken));
                $now = now();

                foreach (array_chunk($fresh, 500) as $chunk) {
                    $rows = [];
                    foreach ($chunk as $code) {
                        $rows[] = [
                            'qr_batch_id' => $batch->id,
                            'code' => $code,
                            'serial' => ++$serial,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    QrCode::insert($rows);
                }

                $remaining -= count($fresh);
            }

            return $batch;
        });
    }

    /** Batch name for the QR codes issued automatically to overseas shops. */
    public const OVERSEAS_BATCH = 'Overseas shops';

    /**
     * One permanent sticker code for a shop outside the UK (they can't order a
     * counter display), mapped to its loyalty card - issued the first time the
     * owner opens their QR codes page. It's a normal /qr/{code} link, so the
     * admin can see, remap or reprint it on the QR codes page, and the card
     * link itself is never shown.
     */
    public function issueFor(Shop $shop): QrCode
    {
        $batch = QrBatch::firstOrCreate(['name' => self::OVERSEAS_BATCH]);

        do {
            $code = $this->randomCode();
        } while (QrCode::where('code', $code)->exists());

        return QrCode::create([
            'qr_batch_id' => $batch->id,
            'shop_id' => $shop->id,
            'code' => $code,
            'destination_url' => route('card.show', $shop),
            'mapped_at' => now(),
        ]);
    }

    private function randomCode(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
