<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmCounterparties\Pages\ListCrmCounterparties;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\User;
use App\Services\Crm\PartnerSpreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PartnerSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_partners_round_trip_through_excel(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'name' => 'Ольга Петрова', 'is_active' => true]);
        $grand = CrmCounterparty::query()->create(['type' => 'organization', 'classification' => 'b2b_partner', 'business_unit' => 'wholesale', 'status' => 'active', 'source' => 'manual',
            'name' => 'Гранд Тепло', 'unp' => '091234567']);
        $grand->contactPoints()->create(['type' => 'phone', 'value' => '+375 29 111-22-33']);
        $grand->contactPoints()->create(['type' => 'email', 'value' => 'old@grand.by']);
        $person = $grand->contacts()->create(['full_name' => 'Иван Сидоров', 'position' => 'директор']);
        CrmContactPoint::query()->create(['contact_id' => $person->id, 'type' => 'phone', 'value' => '+375 44 555-66-77']);
        CrmCounterparty::query()->create(['type' => 'person', 'classification' => 'retail', 'business_unit' => 'retail', 'status' => 'active', 'source' => 'manual', 'name' => 'Розничный Покупатель']);

        $service = app(PartnerSpreadsheet::class);
        $path = $service->export(['b2b_partner']);
        $book = IOFactory::load($path);
        $sheet = $book->getSheet(0);
        $this->assertSame(['ID', 'Название'], [$sheet->getCell('A1')->getValue(), $sheet->getCell('B1')->getValue()]);
        $this->assertSame('Гранд Тепло', $sheet->getCell('B2')->getValue());
        $this->assertSame('091234567', $sheet->getCell('D2')->getValue());
        $this->assertSame('B2B-партнёр', $sheet->getCell('E2')->getValue());
        $this->assertSame('+375 29 111-22-33', $sheet->getCell('L2')->getValue());
        $this->assertStringContainsString('Иван Сидоров — директор — +375 44 555-66-77', (string) $sheet->getCell('N2')->getValue());
        $this->assertSame('', (string) $sheet->getCell('B3')->getValue(), 'Retail customers are not exported.');

        // Unchanged file changes nothing.
        $same = $service->import($path, false);
        $this->assertSame([1, 0, 0, 1], [$same['rows'], $same['updated'], $same['created'], $same['unchanged']]);

        // Manager edits: new phone format, swapped e-mail, status, responsible, a new partner, a bad category.
        $sheet->setCellValue('F2', 'Приостановлен');
        $sheet->setCellValue('I2', 'Ольга Петрова');
        $sheet->setCellValueExplicit('L2', '+375 29 111-22-33, 8 (033) 765-43-21', 'str');
        $sheet->setCellValue('M2', 'new@grand.by');
        $sheet->setCellValue('O2', '7,5');
        $sheet->fromArray([null, 'Тепло Плюс', null, '191111111', 'Неведомая', null, null, 'Брест'], null, 'A3');
        $sheet->setCellValueExplicit('L3', '029 7778899', 'str');
        (new Xlsx($book))->save($path);

        $dry = $service->import($path, true);
        $this->assertSame([2, 1, 1, 1, 1, 1], [$dry['rows'], $dry['updated'], $dry['created'], $dry['phones_added'] - 1, $dry['emails_added'], $dry['emails_off']]);
        $this->assertStringContainsString('Неведомая', implode(' ', $dry['errors']));
        $this->assertSame('active', $grand->refresh()->status, 'Dry run saves nothing.');
        $this->assertSame(2, CrmCounterparty::query()->count());

        $service->import($path, false, $manager->id);
        $grand->refresh();
        $this->assertSame(['paused', $manager->id, '7.50'], [$grand->status, $grand->responsible_user_id, (string) $grand->commercial_discount_percent]);
        $this->assertEqualsCanonicalizing(['+375 29 111-22-33', '+375 33 765-43-21'], $grand->contactPoints()->where('type', 'phone')->where('is_active', true)->pluck('value')->all());
        $this->assertSame(['new@grand.by'], $grand->contactPoints()->where('type', 'email')->where('is_active', true)->pluck('value')->all());
        $this->assertTrue($grand->contactPoints()->where('value', 'old@grand.by')->where('is_active', false)->exists(), 'Removed e-mail is switched off, not deleted.');
        $new = CrmCounterparty::query()->where('name', 'Тепло Плюс')->firstOrFail();
        $this->assertSame(['b2b_partner', '191111111', 'Брест'], [$new->classification, $new->unp, $new->business_region]);
        $this->assertSame(['+375 29 777-88-99'], $new->contactPoints()->pluck('value')->all());
        @unlink($path);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Livewire::test(ListCrmCounterparties::class)->assertActionExists('export_partners')->assertActionExists('import_partners');
    }
}
