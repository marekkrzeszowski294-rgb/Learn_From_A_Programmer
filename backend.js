(() => {
  let sessionPromise;

  async function readResponse(response) {
    let payload;
    try {
      payload = await response.json();
    } catch {
      throw new Error("Serwer zwrócił nieprawidłową odpowiedź.");
    }

    if (!response.ok) {
      const error = new Error(payload.error || "Nie udało się wykonać tej operacji.");
      error.status = response.status;
      throw error;
    }
    return payload;
  }

  async function getSession() {
    if (!sessionPromise) {
      sessionPromise = fetch("api.php?action=session", { credentials: "same-origin" })
        .then(readResponse)
        .catch((error) => {
          sessionPromise = undefined;
          throw error;
        });
    }
    return sessionPromise;
  }

  window.apiRequest = async (action, options = {}) => {
    const session = await getSession();
    const headers = { Accept: "application/json" };
    const request = { ...options, credentials: "same-origin", headers };

    if (options.body !== undefined) {
      headers["Content-Type"] = "application/json";
      headers["X-CSRF-Token"] = session.csrfToken;
      request.body = JSON.stringify(options.body);
    }

    return readResponse(await fetch(`api.php?action=${encodeURIComponent(action)}`, request));
  };
})();
