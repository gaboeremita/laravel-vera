const API_BASE = "";

async function request(url, options = {}) {
    return await fetch(`${API_BASE}${url}`, {
        credentials: 'include',
        redirect: 'manual',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers,
        },
        ...options,
    });
}

export const api = {
    get: (url) => request(url),
    post: (url, data) =>
        request(url, {
            method: "POST",
            body: JSON.stringify(data),
        }),
    delete: (url) =>
        request(url, {
            method: "DELETE",
        }),

    // Auth-specific calls
    getCsrfCookie: () =>
        fetch("/sanctum/csrf-cookie", {
            credentials: "include",
        }),
    login: (email, password) =>
        request("/login", {
            method: "POST",
            body: JSON.stringify({ email, password }),
        }),
    logout: () =>
        request("/logout", {
            method: "POST",
        }),
};
