@extends('layouts.amerce')

@section('title', '404 — Страница не найдена')

@section('content')
<section class="section-404 flat-spacing">
    <div class="container">
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-10 offset-sm-1">
                <div class="image">
                    <img loading="lazy" width="930" height="579"
                        src="{{ asset('assets/images/section/404.svg') }}"
                        alt="Страница не найдена">
                </div>
            </div>
            <div class="col-12">
                <div class="wrap">
                    <div class="content">
                        <h2 class="title">Страница не найдена</h2>
                        <p class="sub-title cl-text-2">Страница удалена или вы перешли по неверной ссылке</p>
                    </div>
                    <div class="group-btn">
                        <a href="{{ url('/') }}" class="tf-btn animate-btn">
                            На главную
                        </a>
                        <a href="{{ url('/catalog') }}" class="tf-btn btn-stroke">
                            Каталог товаров
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
