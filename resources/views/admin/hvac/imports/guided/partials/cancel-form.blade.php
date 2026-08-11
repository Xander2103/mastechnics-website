<form method="POST" action="{{ route('admin.hvac.import.guided.cancel', $token) }}" style="margin-top:0.8rem;">
    @csrf
    <button type="submit" class="admin-link" style="background:none;border:none;cursor:pointer;padding:0;">
        Import annuleren (bestand wordt verwijderd)
    </button>
</form>
