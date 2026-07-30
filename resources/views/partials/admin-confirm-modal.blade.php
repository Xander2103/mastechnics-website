{{--
    Shared admin confirmation modal.

    Opened by any button carrying:
        data-confirm-modal
        data-confirm-title="..."   modal heading
        data-confirm-body="..."    modal body text (plain text, injected via textContent)
        data-confirm-label="..."   label of the destructive confirm button
        data-confirm-form="#id"    selector of the form submitted on confirm

    Behavior lives in initAdminConfirmModal() in resources/js/app.js.
--}}
<div class="admin-confirm-backdrop" id="admin-confirm-backdrop" hidden></div>

<div
    class="admin-confirm-modal"
    id="admin-confirm-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="admin-confirm-title"
    aria-describedby="admin-confirm-body"
    hidden
>
    <div class="admin-confirm-inner">
        <h2 id="admin-confirm-title"></h2>
        <p id="admin-confirm-body"></p>

        <div class="admin-confirm-actions">
            <button type="button" class="button button-secondary" data-confirm-cancel>Annuleren</button>
            <button type="button" class="button admin-button-danger" data-confirm-accept></button>
        </div>
    </div>
</div>
