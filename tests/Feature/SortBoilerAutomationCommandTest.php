<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SortBoilerAutomationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_strays_leave_regulators_and_regulators_get_their_group(): void
    {
        $section = fn (string $slug) => Category::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => 0, 'is_active' => true]);
        $regulators = $section('regulyatory');
        $parts = $section('zapchasti-dlya-kotlov');
        $fans = $section('ventilyatory-nadduva');
        $radiator = $section('radiatornaya-armatura');
        $card = fn (string $name, Category $category) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id, 'price' => 100, 'is_active' => true]);
        $head = $card('Головка термостатическая HERZ “H” m 30x1.5', $regulators);
        $fan = $card('Вентилятор наддува KG Elektronik DPS-120 (200 м³/ч)', $parts);
        $boiler = $card('Контроллер твердотопливного котла KG Elektronik SP-35 PID WiFi (насос ЦО + вентилятор, Wi-Fi)', $regulators);
        $pump = $card('Контроллер насоса ЦО KG Elektronik CS-07.NEW.WIFI', $regulators);
        $room = $card('Комнатный термостат SALUS RT310', $regulators);
        $draught = $card('Регулятор тяги ICMA', $parts);
        $pumpUnit = $card('Блок автоматики', $regulators);
        $burners = $section('pelletnye-gorelki');
        $kipi = $card('вместе с фланцем 15-70 kW', $parts);
        $kipi->forceFill(['meta_title' => 'вместе с фланцем 15-70 kW — купить в %city%'])->save();
        $hopper = $card('Бункер для пеллет ATMOS 500 л', $parts);
        $safety = $card('Термостат предохранительный FONDITAL', $parts);
        $sensors = $section('datchiki');
        $gauges = $section('manometry-i-termometry');
        $outdoor = $card('Датчик уличной температуры (QAC 34), BAXI', $sensors);
        $cylinder = $card('Датчик температуры бойлера Buderus AS1', $sensors);
        $thermometer = $card('Термометр Watts F+R801 d=63 (10005800)', $sensors);
        $glass = $card('Термометр стеклянный технический 100 (0+150°C) СП-2П №3 НЧ', $gauges);
        $valve = $card('Кран шаровый для манометра ММ Ру 40 Ду 15хM20 (вода) ручка флажок LD', $gauges);
        $radio = $card('Радиомодуль LoRaWAN CВC-02 НОРМА', $gauges);
        $meters = $section('schetchiki-vody-i-tepla');
        $woltman = $card('Счетчик холодной воды Вольтмана турбинный WPD-i Ду 80 с импульсным выходом +датчик', $meters);
        $hot = $card('Счетчик горячей воды ETW-м-N Ду 15 Qn 1.6 с датчиком импульсов БелЦЕННЕР арт. 54405', $meters);
        $gas = $section('schetchiki-gaza');
        $hoses = $section('gazovye-shlangi');
        $kata = $card('Счётчик газа БелОМО КАТА – G6 RK – 2 (NB-IoT)', $gas);
        $sgd = $card('Счётчик газа БелОМО СГД-3Т-1-1-G4 (левый), межосевое расстояние 200 мм', $gas);
        $hose = $card('Шланг для газа ADVIXON Г-Ш 1/2"-1/2" (150см)', $gas);
        $steelHose = $card('Газовый шланг ZURFLEX DN16, гайка/штуцер, 0,6 м', $hoses);
        $insert = $card('Вставка диэлектрическая 1/2" ш/ш', $hoses);
        $mixing = $section('smesitelnye-klapany-i-uzly');
        $drainage = $section('drenajnyie');
        $fecal = $card('Насос погружной «ФЕКАЛЬНИК» 115/5', $mixing);
        $tsv = $card('Термостатический клапан Regulus TSV3 65°C', $mixing);
        $barberi = $card('Трехходовой клапан Barberi V07M250 AA', $mixing);
        $vta = $card('Термостатический смесительный клапан ESBE VTA 321(t35-60*c)', $mixing);
        $cvlit = $card('Клапан радиаторный термостат. Ду 15 (прямой) Цветлит', $radiator);
        $kit = $card('Набор термостатический угловой Royal Thermo Design 1/2"М30х1,5 (Черный)', $radiator);
        $bracket = $card('Кронштейн угловой для радиатора белый', $radiator);
        $child = fn (string $slug, Category $parent) => Category::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => $parent->id, 'is_active' => true]);
        $pelletParts = $child('zapchasti-dlya-pelletnyh-gorelok', $burners);
        $grate = $card('OXI Колосник верхнего ряда для пеллетной горелки Ceramik+ 75–1000 кВт', $burners);
        $venma = $card('Пеллетная горелка Venma Comfort R58', $burners);
        $flame = $card('PLUM OCP-2 Оптический датчик пламени, кабель 0,5 м', $pelletParts);
        $leaks = $section('sistemy-zashchity-ot-protechek');
        $leakValves = $child('krany-s-elektroprivodom', $leaks);
        $leakSensors = $child('datchiki-protechki-vody', $leaks);
        $leakValve = $card('Кран с электроприводом Neptun PROFI 12В 1 1/4', $leakValves);
        $leakKit = $card('Система Neptun PROFI Base 3/4', $leaks);
        $salus = $card('Датчик протечки Salus WLS600', $leaks);
        $kits = $section('komplekty-podklyucheniya');
        $herzUnit = $card('Узел подключения HERZ-3000, проходной 3/4"', $kits);
        $mounting = $card('Монтажный комплект для радиатора Rifar Monolit/SupreMO 3/4 (цвет: металлик)', $kits);
        $alarms = $section('signalizatory-zagazovannosti');
        $coAlarm = $card('Детектор загазованности Счетприбор CО (угарный газ) (ДЗС-2)', $alarms);
        $dualAlarm = $card('Детектор загазованности Счетприбор СН4,СО (два газа) (ДЗС-3)', $alarms);
        $methaneAlarm = $card('Сигнализатор загазованности Барьер-CH4', $alarms);
        $cps = $card('Вентилятор наддува СРS-70 (до 70 кВт, 320 м3/час)', $fans);
        $vsk = $card('Вентилятор наддува VSK-160 (к котлам 75-150 кВт)', $fans);
        $multistage = $section('mnogostupenchatye-nasosy');
        $monoblock = $section('konsolnye-i-monoblochnye-nasosy');
        $cv = $card('Многоступенчатый насос Wellmix CV 32-10-2-HQCV (3х380В, 18.5кВт,IE3)', $multistage);
        $pvt = $card('Вертикальный многоступенчатый насос PURITY PVT 4-10 (N)', $multistage);
        $nbw = $card('WELLMIX Моноблочный насос NBW 50-32-250- 7.5/2-380-2S-BQQE', $monoblock);
        $pstg = $card('PURITY Консольно-моноблочный фланцевый насос PSTG 100-250/550 (N)', $monoblock);
        $fc = $card('UNIPUMP Консольно-моноблочный насос FC32-160-1.5', $monoblock);
        $press = $section('press-fitingi');
        $bronzeTee = $card('Тройник бронзовый с внутренней резьбой 22х1/2"х22 пресс SANHA', $press);
        $inoxTee = $card('Varmega VM719181518 18x15x18', $press);
        $inoxUnknown = $card('Varmega VM710002828 28x28', $press);
        $slideElbow = $card('Угольник (угол) 90° с наружной резьбой Varmega Slide-fit VM51418, 32x3/4", аксиальный', $press);
        $copperPlug = $card('Заглушка медная 35 под пайку арт. 109134 VIEGA', $press);
        $acs = $section('kondicionery');
        $wallInverter = $card('Сплит-система Electrolux Skandi DC EACS/I-07HSK/N8_V3', $acs);
        $haier = $card('Кондиционер Haier Quantum AS50HQJ1HRA-B/1U50HQJ1FRA', $acs);
        $onOff = $card('Сплит-система Haier HSU-12HFF103/R3-B / HSU-12HUF103/R3', $acs);
        $cassette = $card('Кассетный кондиционер Electrolux EACC-48H/UP3/N3', $acs);
        $acOutdoor = $card('Внешний блок Electrolux EACO/I-28 FMI-4/N8-ERP', $acs);
        $pp = $section('fitingi-dlya-polipropilenovykh-trub');
        $ppUnion = $card('Американка ПП нар. 32х1 1/4", бел. BAREON', $pp);
        $ppTee = $card('Тройник полипропиленовый переходной 40х32х40 серый PRO AQUA', $pp);
        $ppElbow = $card('Колено ПП комб. вн. 25х3/4", бел. РТП', $pp);
        $ppBypass = $card('Обвод ПП d25, сер.РТП', $pp);
        $ppPlug = $card('Заглушка резьбовая ПП 1/2" PN25, сер. РТП', $pp);
        $balls = $section('sharovye-krany');
        $gasBall = $card('Кран шаровый латунный 11Б27п4 с внутренней/наружной резьбой Ду 32 (газ) ручка-рычаг Цветлит', $balls);
        $inchBall = $card('Кран шаровый 1" вн.-вн. бабочка PN40, LD', $balls);
        $flangeBall = $card('Кран шаровый фланцевый КШЦФ Ру 16 Ду 125/125 LD', $balls);
        $ppBall = $card('Кран шар. ПП 32 сер. РТП', $balls);
        $peBall = $card('Кран шар. 25 х 3/4" нар. (для ПЭ труб), AV Engineering', $balls);
        $faucets = $section('smesiteli');
        $kitchen = $card('Смеситель для кухни (SUS 304) D35, серия ZAR с краном для пит. воды гранит AV Engineering', $faucets);
        $bath = $card('Смеситель для ванны вентильный (тип I), серия 410 ECONOCE', $faucets);
        $sink = $card('Смеситель для умывальника (Lt) D35, серия Lotus черный, a-five', $faucets);
        $aerator = $card('Аэратор для смесителя с НР М24х1/2" MasterProf', $faucets);

        $this->artisan('catalog:sort-boiler-automation')->expectsOutputToContain('metric.status=dry_run')->assertSuccessful();
        $this->assertSame($regulators->id, $head->fresh()->category_id);

        $this->artisan('catalog:sort-boiler-automation', ['--apply' => true])
            ->expectsOutputToContain('metric.boiler_automation.moved=10')
            ->assertSuccessful();

        $this->assertSame($radiator->id, $head->fresh()->category_id);
        $this->assertSame($fans->id, $fan->fresh()->category_id);
        $this->assertSame($regulators->id, $draught->fresh()->category_id);
        $group = fn (Product $product) => DB::table('product_attribute_values')
            ->join('attribute_options', 'attribute_options.id', '=', 'product_attribute_values.option_id')
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.name', 'Назначение')->where('product_id', $product->id)->value('attribute_options.name');
        $this->assertSame('Для твердотопливных котлов', $group($boiler));
        $this->assertSame('Для насосов', $group($pump));
        $this->assertSame('Комнатные термостаты и программаторы', $group($room));
        $this->assertSame('Регуляторы тяги', $group($draught));
        $this->assertSame('Для насосов', $group($pumpUnit));
        $this->assertNull($group($head));
        $this->assertSame($burners->id, $kipi->fresh()->category_id);
        $this->assertSame('Пеллетная горелка KIPI ROTARY 15-70 кВт с фланцем', $kipi->fresh()->name);
        $this->assertSame('Пеллетная горелка KIPI ROTARY 15-70 кВт с фланцем — купить в %city%', $kipi->fresh()->meta_title);
        $this->assertSame('Бункеры для пеллет', $group($hopper));
        $this->assertSame('Запчасти для газовых котлов', $group($safety));
        $this->assertSame('Уличные (наружные) датчики', $group($outdoor));
        $this->assertSame('Датчики бойлера и ГВС', $group($cylinder));
        $this->assertSame($gauges->id, $thermometer->fresh()->category_id);
        $this->assertSame('Термометры стрелочные (биметаллические)', $group($thermometer));
        $this->assertSame('Термометры стеклянные', $group($glass));
        $this->assertSame('Краны для манометров', $group($valve));
        $this->assertSame($meters->id, $radio->fresh()->category_id);
        $this->assertSame('Турбинные счётчики (Вольтмана)', $group($woltman));
        $this->assertSame('Счётчики горячей воды', $group($hot));
        $this->assertSame('Радиомодули', $group($radio));
        $value = fn (string $filter, Product $product) => DB::table('product_attribute_values')
            ->join('attribute_options', 'attribute_options.id', '=', 'product_attribute_values.option_id')
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.name', $filter)->where('product_id', $product->id)->value('attribute_options.name');
        $diameter = fn (Product $product) => $value('Диаметр (Ду)', $product);
        $this->assertSame('Ду 80', $diameter($woltman));
        $this->assertSame('Ду 15', $diameter($hot));
        $this->assertNull($diameter($radio));
        $this->assertSame('БелОМО КАТА', $value('Серия', $kata));
        $this->assertSame('G6', $value('Типоразмер', $kata));
        $this->assertSame('Дистанционная (NB-IoT)', $value('Передача данных', $kata));
        $this->assertSame('БелОМО СГД', $value('Серия', $sgd));
        $this->assertSame('G4', $value('Типоразмер', $sgd));
        $this->assertSame('Без дистанционной передачи', $value('Передача данных', $sgd));
        $this->assertSame($hoses->id, $hose->fresh()->category_id);
        $this->assertSame('Резиновые и полимерные', $value('Тип', $hose));
        $this->assertSame('1,2–1,5 м', $value('Длина', $hose));
        $this->assertSame('Металлические (нержавеющая сталь)', $value('Тип', $steelHose));
        $this->assertSame('До 1 м', $value('Длина', $steelHose));
        $this->assertSame('Диэлектрические вставки', $value('Тип', $insert));
        $this->assertNull($value('Длина', $insert));
        $this->assertSame($drainage->id, $fecal->fresh()->category_id);
        $this->assertSame($mixing->id, $tsv->fresh()->category_id);
        $this->assertSame('Термостатические клапаны для котлов (антиконденсат)', $value('Тип', $tsv));
        $this->assertSame('Трёхходовые клапаны', $value('Тип', $barberi));
        $this->assertSame('Термостатические смесители (ГВС, тёплый пол)', $value('Тип', $vta));
        $this->assertSame('Термостатические клапаны', $value('Тип', $cvlit));
        $this->assertSame('Прямой', $value('Исполнение', $cvlit));
        $this->assertSame('Комплекты и наборы', $value('Тип', $kit));
        $this->assertSame('Угловой', $value('Исполнение', $kit));
        $this->assertSame('Чёрный', $value('Цвет', $kit));
        $this->assertSame('Кронштейны и крепления', $value('Тип', $bracket));
        $this->assertNull($value('Исполнение', $bracket));
        $this->assertSame('Белый', $value('Цвет', $bracket));
        $this->assertSame($pelletParts->id, $grate->fresh()->category_id);
        $this->assertSame('Запчасти и датчики', $value('Тип', $grate));
        $this->assertNull($value('Мощность горелки', $grate));
        $this->assertSame('Запчасти и датчики', $value('Тип', $flame));
        $this->assertSame('Пеллетные горелки', $value('Тип', $venma));
        $this->assertSame('51–100 кВт', $value('Мощность горелки', $venma));
        $this->assertSame('51–100 кВт', $value('Мощность горелки', $kipi));
        $this->assertSame('Краны с электроприводом', $value('Тип', $leakValve));
        $this->assertSame('1 1/4"', $value('Диаметр', $leakValve));
        $this->assertSame('12 В', $value('Напряжение привода', $leakValve));
        $this->assertSame('Готовые комплекты и системы', $value('Тип', $leakKit));
        $this->assertSame('3/4"', $value('Диаметр', $leakKit));
        $this->assertSame($leakSensors->id, $salus->fresh()->category_id);
        $this->assertSame('Датчики протечки', $value('Тип', $salus));
        $this->assertSame('Узлы нижнего подключения', $value('Тип', $herzUnit));
        $this->assertSame('Прямой', $value('Исполнение', $herzUnit));
        $this->assertSame('Монтажные комплекты', $value('Тип', $mounting));
        $this->assertSame('Серебристый', $value('Цвет', $mounting));
        $this->assertSame('Угарный газ (CO)', $value('Какой газ', $coAlarm));
        $this->assertSame('Комбинированные (CO + природный газ)', $value('Какой газ', $dualAlarm));
        $this->assertSame('Природный газ (метан)', $value('Какой газ', $methaneAlarm));
        $this->assertSame('251–400 м³/ч', $value('Производительность', $cps));
        $this->assertSame('26–75 кВт', $value('Для котла мощностью', $cps));
        $this->assertSame('До 250 м³/ч', $value('Производительность', $fan));
        $this->assertNull($value('Для котла мощностью', $fan));
        $this->assertNull($value('Производительность', $vsk));
        $this->assertSame('76–150 кВт', $value('Для котла мощностью', $vsk));
        $this->assertSame('Св. 11 до 30 кВт', $value('Мощность двигателя', $cv));
        $this->assertNull($value('Мощность двигателя', $pvt));
        $this->assertSame('Св. 4 до 11 кВт', $value('Мощность двигателя', $nbw));
        $this->assertSame('Свыше 30 кВт', $value('Мощность двигателя', $pstg));
        $this->assertSame('До 1,5 кВт', $value('Мощность двигателя', $fc));
        $this->assertSame('380–415 В', $value('Питание', $nbw));
        $this->assertSame('Тройники', $value('Тип', $bronzeTee));
        $this->assertSame('Бронза', $value('Материал', $bronzeTee));
        $this->assertSame('22 мм', $value('Диаметр', $bronzeTee));
        $this->assertSame('Тройники', $value('Тип', $inoxTee));
        $this->assertSame('Нержавеющая сталь', $value('Материал', $inoxTee));
        $this->assertSame('18 мм', $value('Диаметр', $inoxTee));
        $this->assertNull($value('Тип', $inoxUnknown));
        $this->assertSame('28 мм', $value('Диаметр', $inoxUnknown));
        $this->assertSame('Угольники и отводы', $value('Тип', $slideElbow));
        $this->assertSame('Латунь', $value('Материал', $slideElbow));
        $this->assertSame('32 мм', $value('Диаметр', $slideElbow));
        $this->assertSame('Заглушки', $value('Тип', $copperPlug));
        $this->assertSame('Медь', $value('Материал', $copperPlug));
        $this->assertSame('35 мм', $value('Диаметр', $copperPlug));
        $this->assertSame('Настенные сплит-системы', $value('Тип', $wallInverter));
        $this->assertSame('До 20 м²', $value('Площадь помещения', $wallInverter));
        $this->assertSame('Инверторный', $value('Компрессор', $wallInverter));
        $this->assertSame('До 50 м²', $value('Площадь помещения', $haier));
        $this->assertSame('Инверторный', $value('Компрессор', $haier));
        $this->assertSame('До 35 м²', $value('Площадь помещения', $onOff));
        $this->assertSame('On/Off', $value('Компрессор', $onOff));
        $this->assertSame('Кассетные', $value('Тип', $cassette));
        $this->assertSame('Свыше 70 м²', $value('Площадь помещения', $cassette));
        $this->assertSame('On/Off', $value('Компрессор', $cassette));
        $this->assertSame('Наружные блоки мульти-сплит', $value('Тип', $acOutdoor));
        $this->assertNull($value('Площадь помещения', $acOutdoor));
        $this->assertSame('Муфты, американки и переходники', $value('Тип', $ppUnion));
        $this->assertSame('32 мм', $value('Диаметр', $ppUnion));
        $this->assertSame('Разборное (американка)', $value('Соединение', $ppUnion));
        $this->assertSame('Белый', $value('Цвет', $ppUnion));
        $this->assertSame('Тройники', $value('Тип', $ppTee));
        $this->assertSame('40 мм', $value('Диаметр', $ppTee));
        $this->assertSame('Под сварку', $value('Соединение', $ppTee));
        $this->assertSame('Серый', $value('Цвет', $ppTee));
        $this->assertSame('Отводы и колена', $value('Тип', $ppElbow));
        $this->assertSame('С внутренней резьбой', $value('Соединение', $ppElbow));
        $this->assertSame('Обводы', $value('Тип', $ppBypass));
        $this->assertSame('25 мм', $value('Диаметр', $ppBypass));
        $this->assertSame('Заглушки', $value('Тип', $ppPlug));
        $this->assertNull($value('Диаметр', $ppPlug));
        $this->assertSame('Внутренняя — наружная резьба', $value('Присоединение', $gasBall));
        $this->assertSame('Ду 32 (1 1/4")', $value('Диаметр (Ду)', $gasBall));
        $this->assertSame('Рычаг', $value('Ручка', $gasBall));
        $this->assertSame('Для газа', $value('Для газа', $gasBall));
        $this->assertSame('Внутренняя — внутренняя резьба', $value('Присоединение', $inchBall));
        $this->assertSame('Ду 25 (1")', $value('Диаметр (Ду)', $inchBall));
        $this->assertSame('Бабочка', $value('Ручка', $inchBall));
        $this->assertNull($value('Для газа', $inchBall));
        $this->assertSame('Фланцевое', $value('Присоединение', $flangeBall));
        $this->assertSame('Ду 125', $value('Диаметр (Ду)', $flangeBall));
        $this->assertSame('Под пластиковую или пресс-трубу', $value('Присоединение', $ppBall));
        $this->assertSame('Ду 25 (1")', $value('Диаметр (Ду)', $ppBall));
        $this->assertSame('Ду 20 (3/4")', $value('Диаметр (Ду)', $peBall));
        $this->assertSame('Для кухни', $value('Назначение', $kitchen));
        $this->assertSame('Однорычажный', $value('Управление', $kitchen));
        $this->assertSame('С краном для питьевой воды', $value('Кран для питьевой воды', $kitchen));
        $this->assertSame('Сатин и нержавеющая сталь', $value('Цвет', $kitchen));
        $this->assertSame('Для ванны', $value('Назначение', $bath));
        $this->assertSame('Двухвентильный', $value('Управление', $bath));
        $this->assertNull($value('Кран для питьевой воды', $bath));
        $this->assertSame('Для умывальника', $value('Назначение', $sink));
        $this->assertSame('Чёрный', $value('Цвет', $sink));
        $this->assertSame('Комплектующие (аэраторы, картриджи, буксы)', $value('Назначение', $aerator));
        $this->assertNull($value('Управление', $aerator));
        $this->assertNull($value('Питание', $pstg));
        $this->assertNull($value('Питание', $cv));
        $this->assertSame(['Для твердотопливных котлов', 'Для котлов с автоподачей (пеллеты, уголь)', 'Для газовых котлов'], DB::table('attribute_options')
            ->join('attributes', 'attributes.id', '=', 'attribute_options.attribute_id')->where('attributes.name', 'Назначение')
            ->where('attributes.category_id', $regulators->id)
            ->orderBy('attribute_options.sort_order')->limit(3)->pluck('attribute_options.name')->all());
        $this->assertTrue((bool) DB::table('attributes')->where(['category_id' => $regulators->id, 'name' => 'Назначение'])->value('in_filter'));

        $this->artisan('catalog:sort-boiler-automation', ['--apply' => true])
            ->expectsOutputToContain('metric.boiler_automation.moved=0')
            ->expectsOutputToContain('metric.boiler_automation.grouped=0')
            ->assertSuccessful();
    }
}
