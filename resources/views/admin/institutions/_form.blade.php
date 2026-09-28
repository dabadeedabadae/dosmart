<form method="POST" action="{{ isset($institution) ? route('admin.institutions.update', $institution) : route('admin.institutions.store') }}">
    @csrf
    @if(isset($institution)) @method('PUT') @endif

    <div class="form-group">
        <label for="institution-name" class="form-label">Название <span style="color:var(--danger-text)">*</span></label>
        <input id="institution-name" type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-error' : '' }}"
               value="{{ old('name', $institution->name ?? '') }}" required maxlength="255" placeholder="Например: АК-159/2">
        @error('name') <div class="form-error">{{ $message }}</div> @enderror
    </div>
    <div class="form-group">
        <label for="institution-city" class="form-label">Город <span style="color:var(--danger-text)">*</span></label>
        <input id="institution-city" type="text" name="city" class="form-control {{ $errors->has('city') ? 'is-error' : '' }}"
               value="{{ old('city', $institution->city ?? '') }}" required maxlength="255" placeholder="Алматы">
        @error('city') <div class="form-error">{{ $message }}</div> @enderror
    </div>
    <div class="form-group">
        <label for="institution-address" class="form-label">Адрес</label>
        <input id="institution-address" type="text" name="address" class="form-control {{ $errors->has('address') ? 'is-error' : '' }}"
               value="{{ old('address', $institution->address ?? '') }}" maxlength="255" placeholder="Улица, дом">
        @error('address') <div class="form-error">{{ $message }}</div> @enderror
    </div>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13.5px;font-weight:500;margin-bottom:22px">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $institution->is_active ?? true) ? 'checked' : '' }}
               style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer">
        Активно (доступно для выбора при оформлении)
    </label>
    @error('is_active') <div class="form-error">{{ $message }}</div> @enderror
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">{{ isset($institution) ? 'Сохранить' : 'Добавить учреждение' }}</button>
        <a href="{{ route('admin.institutions.index') }}" class="btn btn-ghost">Отмена</a>
    </div>
</form>
