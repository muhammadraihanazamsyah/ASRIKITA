@props(['name', 'label', 'value' => '', 'type' => 'text', 'required' => false])
<div>
    <label for="{{ $name }}" class="block text-sm font-semibold">{{ $label }} @if($required)<span aria-hidden="true">*</span>@endif</label>
    @if($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="3" @required($required) aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @error($name) aria-describedby="{{ $name }}-error" @enderror {{ $attributes->class(['mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:outline-2 focus:outline-emerald-700']) }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @error($name) aria-describedby="{{ $name }}-error" @enderror {{ $attributes->class(['mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:outline-2 focus:outline-emerald-700']) }}>
    @endif
    @error($name)<p id="{{ $name }}-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
</div>
