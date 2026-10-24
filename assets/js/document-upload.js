document.getElementById('fileUploadForm')?.addEventListener('submit', function (event) {
    event.preventDefault();
    const file = document.getElementById('uploadFile').files[0];
    if (!file) return;
    const button = this.querySelector('button');
    const progress = document.getElementById('uploadProgress');
    const status = document.getElementById('uploadStatus');
    const endpoint = new URL(this.dataset.endpoint, window.location.href);
    endpoint.searchParams.set('nom', file.name);
    endpoint.searchParams.set('dossier', document.getElementById('uploadFolder').value);
    const request = new XMLHttpRequest();
    request.open('PUT', endpoint);
    request.setRequestHeader('Content-Type', 'application/octet-stream');
    request.setRequestHeader('X-CSRF-Token', this.querySelector('[name="_csrf"]').value);
    request.upload.onprogress = event => {
        if (event.lengthComputable) progress.value = event.loaded / event.total * 100;
        status.textContent = 'Transfert en cours : ' + Math.round(progress.value) + ' %.';
    };
    const evaluationButton = this.dataset.preservePage === 'true'
        ? document.querySelector('.trl-evaluation-form button[type="submit"]') : null;
    const releaseButtons = () => { button.disabled = false; if (evaluationButton) evaluationButton.disabled = false; };
    button.disabled = true;
    if (evaluationButton) evaluationButton.disabled = true;
    progress.hidden = false;
    status.textContent = 'Transfert en cours…';
    request.onload = () => {
        releaseButtons();
        let result;
        try { result = JSON.parse(request.responseText); }
        catch { status.textContent = 'Réponse serveur inattendue (HTTP ' + request.status + '). Enregistrement non confirmé.'; return; }
        status.textContent = result.message;
        if (request.status >= 200 && request.status < 300 && result.ok) {
            if (this.dataset.preservePage !== 'true') { window.location.reload(); return; }
            const list = document.getElementById('trl-evidence-list');
            if (list && Number.isInteger(result.document_id) && result.document_id > 0) {
                const link = document.createElement('a');
                const download = new URL(this.dataset.endpoint, window.location.href);
                download.searchParams.delete('usage');
                download.searchParams.set('document', String(result.document_id));
                link.href = download.href;
                link.textContent = file.name;
                const item = document.createElement('li');
                item.append(link);
                list.prepend(item);
                document.getElementById('trl-evidence-empty')?.setAttribute('hidden', '');
            }
            document.getElementById('uploadFile').value = '';
            status.textContent = result.message + ' Vous pouvez enregistrer l’évaluation ; votre texte a été conservé.';
        }
    };
    request.onerror = () => {
        releaseButtons();
        status.textContent = 'Connexion interrompue. Vérifiez Nextcloud avant de réessayer.';
    };
    request.send(file);
});
