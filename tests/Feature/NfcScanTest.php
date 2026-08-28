<?php

namespace Tests\Feature;

use Tests\TestCase;

class NfcScanTest extends TestCase
{
    public function test_it_returns_not_registered_for_unknown_uid(): void
    {
        $response = $this->postJson('/api/nfc/scan', [
            'uid' => 'UNKNOWN_UID',
            'terminal_id' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'NFC card is not registered.',
            ]);
    }
}
