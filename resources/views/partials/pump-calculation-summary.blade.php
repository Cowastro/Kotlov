@if(!empty($pumpCalculations))
<section class="border rounded-4 p-3 mb-3" aria-label="Сохранённые расчёты насосов">
    <h2 style="font-size:18px;margin-bottom:10px">Расчёты циркуляционных насосов</h2>
    @foreach($pumpCalculations as $calculation)
        @continue(!is_array($calculation) || !isset($calculation['result']['summary']))
        <div style="font-size:12px;line-height:1.6;margin-bottom:12px">
            <strong>{{ $calculation['result']['summary'] }}</strong>
            @php $parameters=$calculation['input'] ?? []; @endphp
            <p>@foreach(['power'=>'Нагрузка, кВт','area'=>'Площадь, м²','heat_loss'=>'Теплопотери, Вт/м²','supply'=>'Подача, °C','return'=>'Обратка, °C','pipe_length'=>'Длина контура, м','resistance'=>'R, Па/м','factor'=>'ZF','connection'=>'Присоединение','voltage'=>'Питание, В','length'=>'Монтажная длина, мм','model'=>'Исходная модель'] as $key=>$label)@if(isset($parameters[$key]) && $parameters[$key]!==''){{ $label }}: {{ $parameters[$key] }}; @endif @endforeach</p>
            <a href="{{ route('pump-calculator', array_merge($parameters,['run'=>1])) }}" style="text-decoration:underline">Открыть параметры расчёта →</a>
        </div>
    @endforeach
    <small>Это предварительный подбор. Рабочую кривую, назначение, присоединения и теплоноситель сверяют по паспорту производителя.</small>
</section>
@endif
