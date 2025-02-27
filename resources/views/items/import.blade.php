@extends('layouts.app')

@section('content')

    <section class="module-container">
        <header>
            <div class="section-title">{{ __('import.title') }}</div>
            <div class="module-actions">
                <button type="submit"class="button"><i class="fa fa-save"></i><span>{{ __('import.save') }}</span></button>
                <a href="{{ route('settings.index', []) }}" class="button"><i class="fa fa-ban"></i><span>{{ __('app.buttons.cancel') }}</span></a>
            </div>
        </header>
        <div class="create">
            {!! csrf_field() !!}

            <div class="input">
                <input class="form-control" name="import" type="file">
            </div>

        </div>
        <footer>
            <div class="section-title">&nbsp;</div>
            <div class="module-actions">
                <button type="submit"class="button"><i class="fa fa-save"></i><span>{{ __('import.save') }}</span></button>
                <a href="{{ route('settings.index', []) }}" class="button"><i class="fa fa-ban"></i><span>{{ __('app.buttons.cancel') }}</span></a>
            </div>
        </footer>

    </section>


@endsection