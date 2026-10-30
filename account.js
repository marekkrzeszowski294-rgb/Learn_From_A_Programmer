const loginForm = document.querySelector("[data-login-form]");
const registrationForm = document.querySelector("[data-registration-form]");
const logoutButton = document.querySelector("[data-logout]");
const dashboardRole = document.body.dataset.dashboardRole;

function showFormMessage(element, message, isError = false) {
  if (!element) return;
  element.textContent = message;
  element.hidden = false;
  element.dataset.state = isError ? "error" : "success";
}

function dashboardPath(role) {
  return role === "mentor" ? "dashboard-mentor.html" : "dashboard-uczen.html";
}

if (loginForm) {
  const message = loginForm.querySelector("[data-form-message]");
  loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!loginForm.reportValidity()) return;

    const formData = new FormData(loginForm);
    try {
      const result = await window.apiRequest("login", {
        method: "POST",
        body: {
          email: formData.get("email"),
          password: formData.get("password"),
          role: formData.get("role"),
          remember: formData.has("remember"),
        },
      });
      window.location.href = dashboardPath(result.user.role);
    } catch (error) {
      showFormMessage(message, error.message, true);
    }
  });
}

if (registrationForm) {
  const message = registrationForm.querySelector("[data-form-message]");
  const password = registrationForm.querySelector('[name="password"]');
  const passwordConfirmation = registrationForm.querySelector('[name="passwordConfirmation"]');

  const validatePasswordConfirmation = () => {
    passwordConfirmation.setCustomValidity(
      passwordConfirmation.value && passwordConfirmation.value !== password.value
        ? "Hasła muszą być takie same."
        : ""
    );
  };
  passwordConfirmation.addEventListener("input", validatePasswordConfirmation);
  password.addEventListener("input", validatePasswordConfirmation);

  registrationForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!registrationForm.reportValidity()) return;

    const formData = new FormData(registrationForm);
    try {
      const result = await window.apiRequest("register", {
        method: "POST",
        body: {
          name: formData.get("name"),
          email: formData.get("email"),
          password: formData.get("password"),
          role: formData.get("role"),
          terms: formData.has("terms"),
        },
      });
      window.location.href = dashboardPath(result.user.role);
    } catch (error) {
      showFormMessage(message, error.message, true);
    }
  });
}

if (logoutButton) {
  logoutButton.addEventListener("click", async () => {
    logoutButton.disabled = true;
    try {
      await window.apiRequest("logout", { method: "POST" });
      window.location.href = "login.html";
    } catch (error) {
      logoutButton.disabled = false;
      const message = document.querySelector("[data-dashboard-message]");
      showFormMessage(message, error.message, true);
    }
  });
}

function updateStudentProgress(completedLessons) {
  const completedLessonsElement = document.querySelector("[data-completed-lessons]");
  const completeLesson = document.querySelector("[data-complete-lesson]");
  const checkpoint = document.querySelector("[data-checkpoint]");
  const beforeCheckpoint = document.querySelector("[data-before-checkpoint]");

  if (completedLessonsElement) completedLessonsElement.textContent = String(completedLessons);
  if (checkpoint) checkpoint.hidden = completedLessons < 3;
  if (beforeCheckpoint) beforeCheckpoint.hidden = completedLessons >= 3;
  if (completeLesson) {
    completeLesson.disabled = completedLessons >= 3;
    completeLesson.textContent = completedLessons >= 3
      ? "Punkt kontrolny gotowy"
      : "Oznacz kolejną lekcję jako zakończoną";
  }

  document.querySelectorAll("[data-student-lesson]").forEach((lesson) => {
    const number = Number(lesson.dataset.studentLesson);
    const isComplete = number <= completedLessons;
    const icon = lesson.querySelector("[data-lesson-icon]");
    const status = lesson.querySelector("[data-lesson-status]");
    const badge = lesson.querySelector("[data-lesson-badge]");
    if (icon) icon.textContent = isComplete ? "✓" : String(number);
    if (status) status.textContent = isComplete ? "zakończona" : "do zrealizowania";
    if (badge) {
      badge.textContent = isComplete ? "Gotowe" : "Do zrobienia";
      badge.classList.toggle("status--done", isComplete);
      badge.classList.toggle("status--waiting", !isComplete);
    }
  });
}

function renderBookingRequests(requests, container, emptyMessage) {
  if (!container) return;
  container.replaceChildren();

  if (!requests.length) {
    const emptyState = document.createElement("p");
    emptyState.className = "quiet-note";
    emptyState.textContent = emptyMessage;
    container.append(emptyState);
    return;
  }

  requests.forEach((request) => {
    const article = document.createElement("article");
    article.className = "session-card";
    const content = document.createElement("div");
    content.className = "session-main";
    const text = document.createElement("span");
    text.className = "session-text";
    const title = document.createElement("strong");
    title.textContent = request.name || request.topic;
    const details = document.createElement("small");
    const date = new Date(request.created_at.replace(" ", "T"));
    const dateText = Number.isNaN(date.getTime()) ? "" : date.toLocaleString("pl-PL");
    details.textContent = request.email
      ? `${request.email} · ${request.topic} · ${request.mentor_level} · ${dateText}`
      : `${request.topic} · ${request.mentor_level} · ${dateText}`;
    text.append(title, details);
    content.append(text);
    const status = document.createElement("span");
    status.className = "status status--waiting";
    status.textContent = request.status === "new" ? "Nowe zgłoszenie" : request.status;
    article.append(content, status);
    container.append(article);
  });
}

async function loadDashboard() {
  try {
    const result = await window.apiRequest("dashboard");
    if (result.user.role !== dashboardRole) {
      window.location.replace(dashboardPath(result.user.role));
      return;
    }

    const heading = document.querySelector("[data-dashboard-heading]");
    if (heading) {
      const firstName = result.user.name.trim().split(/\s+/)[0];
      heading.textContent = dashboardRole === "mentor"
        ? `Dzień dobry, ${firstName}!`
        : `Cześć, ${firstName}!`;
    }

    if (dashboardRole === "student") {
      updateStudentProgress(result.user.completedLessons);
      const requestCount = document.querySelector("[data-student-request-count]");
      if (requestCount) requestCount.textContent = String(result.requests.length);
      renderBookingRequests(
        result.requests,
        document.querySelector("[data-student-bookings]"),
        "Nie masz jeszcze zgłoszeń. Możesz poprosić o rozmowę na stronie głównej."
      );
    } else {
      const requestCount = document.querySelector("[data-request-count]");
      if (requestCount) requestCount.textContent = String(result.requests.length);
      renderBookingRequests(
        result.requests,
        document.querySelector("[data-mentor-requests]"),
        "Nie masz jeszcze przypisanych zgłoszeń."
      );
    }
  } catch (error) {
    if (error.status === 401) {
      window.location.replace("login.html");
      return;
    }
    showFormMessage(document.querySelector("[data-dashboard-message]"), error.message, true);
  }
}

if (dashboardRole) {
  loadDashboard();

  const completeLesson = document.querySelector("[data-complete-lesson]");
  completeLesson?.addEventListener("click", async () => {
    completeLesson.disabled = true;
    try {
      const result = await window.apiRequest("complete-lesson", { method: "POST" });
      updateStudentProgress(result.user.completedLessons);
    } catch (error) {
      completeLesson.disabled = false;
      showFormMessage(document.querySelector("[data-dashboard-message]"), error.message, true);
    }
  });
}
