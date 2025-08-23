@extends('livewire.product-wizard.shell')

@section('step-content')
    @if($currentStep === 1)
        @include('livewire.product-wizard.step-1-product-info')
    @elseif($currentStep === 2)
        @include('livewire.product-wizard.step-2-variants')
    @elseif($currentStep === 3)
        @include('livewire.product-wizard.step-3-images')
    @elseif($currentStep === 4)
        @include('livewire.product-wizard.step-4-pricing')
    @endif
@endsection