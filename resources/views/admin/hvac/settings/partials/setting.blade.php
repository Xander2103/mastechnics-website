{{--
    One guided setting. Expects: $entry (decorated rule), $ruleSet (the set being shown),
    $concept (draft rule set or null — editing only happens in a concept).
--}}
@php
    $guide = $entry['guide'];
    $validation = $entry['validation'];
    $isConfirmed = $entry['status'] === 'validated';
    $uid = 'qs-' . substr(md5($entry['key']), 0, 8);
    $isTable = count($entry['value_lines']) > 1;
    $editErrorHere = old('rule_key') === $entry['key'] && old('value') !== null;
@endphp

<article class="qs-setting @if ($entry['critical']) qs-setting--important @endif" id="{{ $uid }}" aria-labelledby="{{ $uid }}-name">
    <div class="qs-setting__head">
        <div>
            <h4 class="qs-setting__name" id="{{ $uid }}-name">{{ $guide['name'] }}</h4>
            @if ($entry['critical'])
                <span class="qs-tag" title="Deze instelling moet bevestigd zijn voordat u een aanbeveling kunt goedkeuren.">Belangrijk</span>
            @endif
        </div>
        <span class="qs-status qs-status--{{ $entry['friendly']['key'] }}">{{ $entry['friendly']['label'] }}</span>
    </div>

    <p class="qs-setting__value @if ($isTable) qs-setting__value--table @endif">
        <span class="qs-muted" style="font-size:0.78rem;font-weight:800;text-transform:uppercase;letter-spacing:0.03em;">Huidige waarde</span>
        @foreach ($entry['value_lines'] as $line)
            <span>{{ $line }}</span>
        @endforeach
    </p>
    <p class="qs-muted">{{ $entry['status_reason'] }}</p>

    <dl class="qs-setting__facts">
        <div>
            <dt>Wat betekent deze waarde?</dt>
            <dd>{{ $guide['meaning'] }}</dd>
        </div>
        <div>
            <dt>Waarvoor wordt ze gebruikt?</dt>
            <dd>{{ $guide['used_for'] }}</dd>
        </div>
        @if ($guide['example'])
            <div class="qs-wide">
                <dt>Praktijkvoorbeeld</dt>
                <dd class="qs-example">{{ $guide['example'] }}</dd>
            </div>
        @endif
        <div class="qs-wide">
            <dt>Gevolg van een verkeerde instelling</dt>
            <dd>{{ $guide['consequence'] }}</dd>
        </div>
    </dl>

    <div class="qs-actions">
        @if ($concept)
            @if ($entry['edit']['editable'])
                <form method="POST" action="{{ route('admin.hvac.rules.value', $concept) }}" class="qs-inline-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="rule_key" value="{{ $entry['key'] }}">
                    <label class="qs-field @if ($editErrorHere && $errors->has('value')) qs-field--error @endif" for="{{ $uid }}-value">
                        Nieuwe waarde ({{ $entry['unit'] }})
                        @if (isset($entry['edit']['choices']))
                            <select name="value" id="{{ $uid }}-value">
                                @foreach ($entry['edit']['choices'] as $choice)
                                    <option value="{{ $choice }}" @selected((float) $entry['raw_value'] === (float) $choice)>{{ \App\Services\Hvac\HvacSettingsGuide::nl($choice) }}%</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" inputmode="decimal" name="value" id="{{ $uid }}-value"
                                   value="{{ $editErrorHere ? old('value') : \App\Services\Hvac\HvacSettingsGuide::nl($entry['raw_value']) }}"
                                   aria-describedby="{{ $uid }}-hint" autocomplete="off">
                            <span class="qs-hint" id="{{ $uid }}-hint">
                                Tussen {{ \App\Services\Hvac\HvacSettingsGuide::nl($entry['edit']['min']) }} en {{ \App\Services\Hvac\HvacSettingsGuide::nl($entry['edit']['max']) }}. Een komma mag.
                            </span>
                        @endif
                    </label>
                    <button type="submit" class="button button-secondary">Waarde opslaan in concept</button>
                </form>
            @else
                <p class="qs-muted">{{ $entry['edit']['reason'] }}</p>
            @endif
        @endif

        @if ($isConfirmed)
            <div>
                <p class="qs-confirmed">
                    ✓ Bevestigd door {{ $validation->validated_by ?: 'onbekend' }}
                    op {{ $validation->validated_at?->format('d/m/Y \o\m H:i') }}
                    — instellingen versie {{ $ruleSet->version }}.
                </p>
                @if ($validation->note)
                    <p class="qs-muted">Notitie: “{{ $validation->note }}”</p>
                @endif
                <form method="POST" action="{{ route('admin.hvac.rules.unvalidate') }}">
                    @csrf
                    <input type="hidden" name="rule_key" value="{{ $entry['key'] }}">
                    <input type="hidden" name="rule_set" value="{{ $ruleSet->id }}">
                    <button type="submit" class="qs-linkbutton">Bevestiging intrekken</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('admin.hvac.rules.validate') }}" class="qs-confirm">
                @csrf
                <input type="hidden" name="rule_key" value="{{ $entry['key'] }}">
                <input type="hidden" name="rule_set" value="{{ $ruleSet->id }}">
                <label class="qs-check" for="{{ $uid }}-confirm">
                    <input type="checkbox" name="confirm" value="1" id="{{ $uid }}-confirm" required>
                    <span>{{ $guide['confirm_label'] }}</span>
                </label>
                <label class="qs-field" for="{{ $uid }}-note">
                    Notitie voor uzelf (optioneel)
                    <input type="text" name="note" id="{{ $uid }}-note" maxlength="1000" placeholder="Bijv. afgesproken tarief 2026">
                </label>
                <button type="submit" class="button button-primary">Bevestigen</button>
            </form>
        @endif
    </div>
</article>
