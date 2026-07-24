/**
 * Kleine fetch-wrapper voor de JSON-API (/api/v1/...). CSRF-header wordt al automatisch toegevoegd
 * door csrf.js (monkey-patcht window.fetch), dus die hoeft hier niet apart geregeld te worden.
 * Elke succesvolle response volgt de envelope {status:"success", data, meta?}; bij een fout gooit
 * dit een ApiError met .status/.message/.errors zodat aanroepers dat één keer hoeven af te vangen.
 */
export class ApiError extends Error {
    constructor(status, message, errors) {
        super(message);
        this.status = status;
        this.errors = errors || {};
    }
}

async function request(method, path, body) {
    const init = { method, headers: { 'Accept': 'application/json' } };
    if (body !== undefined) {
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(body);
    }

    const response = await fetch(path, init);

    if (response.status === 401) {
        window.location.href = '/login';
        return new Promise(() => {});
    }

    let payload = null;
    try {
        payload = await response.json();
    } catch (e) {
        throw new ApiError(response.status, 'Onverwacht antwoord van de server.');
    }

    if (!response.ok || payload.status === 'error') {
        throw new ApiError(response.status, payload.message || 'Er ging iets mis.', payload.errors);
    }

    return payload;
}

export const api = {
    get: (path) => request('GET', path),
    post: (path, body) => request('POST', path, body ?? {}),
    put: (path, body) => request('PUT', path, body ?? {}),
    del: (path) => request('DELETE', path),
};
