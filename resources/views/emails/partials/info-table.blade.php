{{-- Expects: $title (string), $rows (assoc array label => value) --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; border: 1px solid #d9e4ef; border-radius: 14px; overflow: hidden;">
    <tr>
        <td colspan="2" style="padding: 14px 18px; background: #edf5ff; color: #0f3557; font-weight: bold;">
            {{ $title }}
        </td>
    </tr>

    @foreach ($rows as $label => $value)
        <tr>
            <td style="width: 38%; padding: 12px 18px; border-top: 1px solid #d9e4ef; color: #6b7c8f; font-weight: bold; vertical-align: top;">
                {{ $label }}
            </td>

            <td style="padding: 12px 18px; border-top: 1px solid #d9e4ef; vertical-align: top; color: #102033;">
                {{ $value }}
            </td>
        </tr>
    @endforeach
</table>
