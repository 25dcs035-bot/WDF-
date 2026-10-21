<?php
declare(strict_types=1);

require_once __DIR__ . "/../PRACTICAL-10/auth.php";
requireLogin();

if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: ../PRACTICAL-10/dashboard.php");
    exit;
}

$_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events API - Student Hub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #eef2f6; color: #333; font-family: Arial, sans-serif; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .logo { display: flex; align-items: center; gap: 12px; font-size: 1.4rem; font-weight: bold; }
        .logo img { width: 45px; height: 45px; border-radius: 6px; object-fit: cover; }
        nav { display: flex; align-items: center; gap: 10px; }
        nav a { padding: 8px 12px; border-radius: 6px; color: #4a4a4a; font-weight: bold; text-decoration: none; }
        nav a:hover, nav a.active { background: #4a4a4a; color: #fff; }
        main { max-width: 1100px; margin: 32px auto; padding: 0 20px; }
        .card { margin-bottom: 20px; padding: 24px; border-radius: 10px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        h1, h2 { margin-bottom: 14px; }
        .message { display: none; margin-bottom: 18px; padding: 12px 14px; border-radius: 6px; background: #e8f5ed; color: #17653a; }
        .message.error { background: #fdecea; color: #a12622; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #c8cdd3; border-radius: 5px; font: inherit; }
        textarea { min-height: 100px; resize: vertical; }
        .actions { display: flex; align-items: center; gap: 8px; }
        button, .button { display: inline-block; padding: 10px 14px; border: 0; border-radius: 5px; background: #4a4a4a; color: #fff; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
        button:hover, .button:hover { background: #333; }
        .secondary { background: #68717c; }
        .danger { background: #a12622; }
        .events { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .event { padding: 18px; border: 1px solid #e0e0e0; border-radius: 8px; overflow-wrap: anywhere; }
        .event h3 { margin-bottom: 8px; }
        .event p { margin: 6px 0; line-height: 1.5; white-space: pre-wrap; }
        .event-actions { display: flex; gap: 8px; margin-top: 14px; }
        .empty { color: #555; }
        @media (max-width: 700px) { header { flex-direction: column; gap: 12px; padding: 15px; } nav { flex-wrap: wrap; justify-content: center; } }
        @media (max-width: 520px) { .form-grid { grid-template-columns: 1fr; } .full { grid-column: auto; } .card { padding: 18px; } }
    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="../logo.jpg" alt="Student Hub Logo">Student Hub Portal</div>
        <nav aria-label="Main navigation">
            <a href="../home.html">Home</a>
            <a href="../PRACTICAL-10/admin_dashboard.php">Admin Dashboard</a>
            <a href="../PRACTICAL-12/events.php">Events</a>
            <a href="index.php" class="active" aria-current="page">Events API</a>
            <form method="post" action="../PRACTICAL-10/logout.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                <button type="submit">Logout</button>
            </form>
        </nav>
    </header>

    <main>
        <h1>Event Management with Fetch API</h1>
        <div id="message" class="message" role="status" aria-live="polite"></div>

        <section class="card">
            <h2 id="formTitle">Add event</h2>
            <form id="eventForm">
                <input type="hidden" id="eventId" value="">
                <div class="form-grid">
                    <div><label for="title">Event title</label><input id="title" name="title" required maxlength="150"></div>
                    <div><label for="category">Category</label><input id="category" name="category" required maxlength="60"></div>
                    <div><label for="location">Location</label><input id="location" name="location" required maxlength="150"></div>
                    <div><label for="event_date">Date and time</label><input id="event_date" name="event_date" type="datetime-local" required></div>
                    <div class="full"><label for="description">Description</label><textarea id="description" name="description" required maxlength="5000"></textarea></div>
                    <div class="full actions">
                        <button id="saveButton" type="submit">Add event</button>
                        <button id="cancelButton" class="secondary" type="button" hidden>Cancel edit</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Events</h2>
            <div id="events" class="events"><p class="empty">Loading events...</p></div>
        </section>
    </main>

    <script>
        const apiUrl = "api/events.php";
        const csrfToken = <?= json_encode($_SESSION["csrf_token"]) ?>;
        const form = document.getElementById("eventForm");
        const eventsContainer = document.getElementById("events");
        const messageBox = document.getElementById("message");
        const eventId = document.getElementById("eventId");
        const formTitle = document.getElementById("formTitle");
        const saveButton = document.getElementById("saveButton");
        const cancelButton = document.getElementById("cancelButton");

        function showMessage(message, isError = false) {
            messageBox.textContent = message;
            messageBox.classList.toggle("error", isError);
            messageBox.style.display = "block";
        }

        async function requestApi(method, data = null) {
            const options = { method, headers: { "Accept": "application/json" } };
            if (data !== null) {
                options.headers["Content-Type"] = "application/json";
                options.headers["X-CSRF-Token"] = csrfToken;
                options.body = JSON.stringify(data);
            }

            const response = await fetch(apiUrl, options);
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || "The request failed.");
            }
            return result;
        }

        function addText(parent, tagName, text, className = "") {
            const element = document.createElement(tagName);
            element.textContent = text;
            if (className) element.className = className;
            parent.appendChild(element);
            return element;
        }

        function renderEvents(events) {
            eventsContainer.replaceChildren();
            if (events.length === 0) {
                addText(eventsContainer, "p", "No events found. Add an event using the form above.", "empty");
                return;
            }

            events.forEach(event => {
                const card = document.createElement("article");
                card.className = "event";
                addText(card, "h3", event.title);
                addText(card, "p", event.category);
                addText(card, "p", event.description);
                addText(card, "p", "When: " + event.event_date);
                addText(card, "p", "Where: " + event.location);

                const actions = document.createElement("div");
                actions.className = "event-actions";
                const editButton = addText(actions, "button", "Edit", "secondary");
                editButton.type = "button";
                editButton.addEventListener("click", () => startEdit(event));

                const deleteButton = addText(actions, "button", "Delete", "danger");
                deleteButton.type = "button";
                deleteButton.addEventListener("click", () => deleteEvent(event.id));
                card.appendChild(actions);
                eventsContainer.appendChild(card);
            });
        }

        async function loadEvents() {
            try {
                const result = await requestApi("GET");
                renderEvents(result.events);
            } catch (error) {
                eventsContainer.replaceChildren();
                addText(eventsContainer, "p", error.message, "empty");
                showMessage(error.message, true);
            }
        }

        function startEdit(event) {
            eventId.value = event.id;
            form.elements.title.value = event.title;
            form.elements.category.value = event.category;
            form.elements.description.value = event.description;
            form.elements.location.value = event.location;
            form.elements.event_date.value = event.event_date.replace(" ", "T").slice(0, 16);
            formTitle.textContent = "Edit event";
            saveButton.textContent = "Save changes";
            cancelButton.hidden = false;
            form.scrollIntoView({ behavior: "smooth", block: "start" });
        }

        function resetForm() {
            form.reset();
            eventId.value = "";
            formTitle.textContent = "Add event";
            saveButton.textContent = "Add event";
            cancelButton.hidden = true;
        }

        async function deleteEvent(id) {
            if (!window.confirm("Delete this event?")) return;
            try {
                const result = await requestApi("DELETE", { id });
                showMessage(result.message);
                await loadEvents();
            } catch (error) {
                showMessage(error.message, true);
            }
        }

        form.addEventListener("submit", async event => {
            event.preventDefault();
            const id = eventId.value;
            const data = {
                title: form.elements.title.value.trim(),
                category: form.elements.category.value.trim(),
                description: form.elements.description.value.trim(),
                location: form.elements.location.value.trim(),
                event_date: form.elements.event_date.value
            };
            if (id) data.id = Number(id);

            try {
                const result = await requestApi(id ? "PUT" : "POST", data);
                showMessage(result.message);
                resetForm();
                await loadEvents();
            } catch (error) {
                showMessage(error.message, true);
            }
        });

        cancelButton.addEventListener("click", resetForm);
        loadEvents();
    </script>
</body>
</html>
