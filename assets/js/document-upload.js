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
    button.disabled = true;
    progress.hidden = false;
    status.textContent = 'Transfert en cours…';
    request.onload = () => {
        button.disabled = false;
        let result;
        try { result = JSON.parse(request.responseText); }
        catch { status.textContent = 'Réponse serveur inattendue (HTTP ' + request.status + '). Enregistrement non confirmé.'; return; }
        status.textContent = result.message;
        if (request.status >= 200 && request.status < 300 && result.ok) window.location.reload();
    };
    request.onerror = () => {
        button.disabled = false;
        status.textContent = 'Connexion interrompue. Vérifiez Nextcloud avant de réessayer.';
    };
    request.send(file);
});
