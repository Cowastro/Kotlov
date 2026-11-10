<?php

namespace Tests\Feature;

use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizePhonesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_formatted_as_plus_375(): void
    {
        $this->assertSame('+375 29 264-64-82', PhoneNumber::format('8029-264-64-82'));
        $this->assertSame('+375 17 362-79-45', PhoneNumber::format('+375173627945'));
        $this->assertSame('+375 1593 7-07-13', PhoneNumber::format('(01593)7-07-13'));
        $this->assertSame('+375 216 22-97-02', PhoneNumber::format('8 (0216) 229702'));
        $this->assertSame('+375 29 828-37-66', PhoneNumber::format('375298283766'));
        $this->assertNull(PhoneNumber::format('+7 916 123-45-67'));
        $this->assertSame(['phones' => ['+375 29 672-45-18'], 'note' => 'доб. 7724518'], PhoneNumber::extract('+375 (29) 6724518, доб. 7724518'));
    }

    public function test_command_formats_splits_and_keeps_foreign_numbers(): void
    {
        $c = CrmCounterparty::query()->create(['type' => 'organization', 'classification' => 'b2b_partner', 'status' => 'active', 'source' => 'manual', 'name' => 'Альфамагер']);
        $ids = [];
        foreach (['801562 50655, 029 6645244', '+375173627945 тел/факс', '+7 916 123-45-67', '80291112233'] as $value) {
            $ids[] = DB::table('crm_contact_points')->insertGetId(['counterparty_id' => $c->id, 'type' => 'phone', 'value' => $value, 'normalized_value' => '', 'is_primary' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->artisan('crm:normalize-phones')->assertSuccessful();
        $this->assertSame('80291112233', CrmContactPoint::query()->find($ids[3])->value);

        $this->artisan('crm:normalize-phones --apply')->assertSuccessful();
        $points = CrmContactPoint::query()->where('counterparty_id', $c->id)->orderBy('id')->get();
        $this->assertSame(['+375 156 25-06-55', '+375 17 362-79-45', '+7 916 123-45-67', '+375 29 111-22-33', '+375 29 664-52-44'], $points->pluck('value')->all());
        $this->assertSame('тел/факс', $points[1]->label);
        $this->assertSame('375291112233', $points[3]->normalized_value);

        // New numbers are stored in the standard form right away.
        $this->assertSame('+375 44 511-06-41', $c->contactPoints()->create(['type' => 'phone', 'value' => '8 044 5110641'])->value);
    }
}
