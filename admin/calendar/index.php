<?php
require_once '../../includes/header.php';
require_once '../../includes/Calendar.php';

if (!is_admin()) {
    header("Location: ../../login.php");
    exit();
}

$calendar = new Calendar($conn);
$events = $calendar->getEvents();
?>

<div class="admin-container">
    <?php require_once '../sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="calendar-container">
            <div class="calendar-header">
                <h2>Calendario de Eventos</h2>
                <button type="button" class="btn btn-primary" onclick="showEventModal()">
                    Nuevo Evento
                </button>
            </div>
            
            <div class="calendar-filters">
                <div class="form-group">
                    <label>Filtrar por tipo:</label>
                    <select id="event-type-filter" onchange="filterEvents()">
                        <option value="">Todos</option>
                        <option value="course">Cursos</option>
                        <option value="workshop">Talleres</option>
                        <option value="meeting">Reuniones</option>
                        <option value="other">Otros</option>
                    </select>
                </div>
            </div>
            
            <div id="calendar"></div>
        </div>
    </div>
</div>

<!-- Modal para crear/editar eventos -->
<div class="modal" id="event-modal">
    <div class="modal-content">
        <h3>Evento</h3>
        <form id="event-form">
            <input type="hidden" id="event-id">
            
            <div class="form-group">
                <label for="event-title">Título *</label>
                <input type="text" id="event-title" required>
            </div>
            
            <div class="form-group">
                <label for="event-description">Descripción</label>
                <textarea id="event-description"></textarea>
            </div>
            
            <div class="form-group">
                <label for="event-course">Curso</label>
                <select id="event-course">
                    <option value="">Seleccionar curso</option>
                    <?php foreach ($calendar->getCourses() as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event-start">Inicio *</label>
                    <input type="datetime-local" id="event-start" required>
                </div>
                
                <div class="form-group">
                    <label for="event-end">Fin *</label>
                    <input type="datetime-local" id="event-end" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="event-type">Tipo</label>
                <select id="event-type">
                    <option value="course">Curso</option>
                    <option value="workshop">Taller</option>
                    <option value="meeting">Reunión</option>
                    <option value="other">Otro</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="event-color">Color</label>
                <input type="color" id="event-color" value="#007bff">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="button" class="btn btn-secondary" onclick="closeEventModal()">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- FullCalendar y scripts -->
<link href='https://cdn.jsdelivr.net/npm/@fullcalendar/core@4.4.0/main.min.css' rel='stylesheet' />
<link href='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@4.4.0/main.min.css' rel='stylesheet' />
<link href='https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@4.4.0/main.min.css' rel='stylesheet' />

<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@4.4.0/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@4.4.0/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@4.4.0/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@4.4.0/main.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: ['dayGrid', 'timeGrid', 'interaction'],
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        defaultView: 'dayGridMonth',
        locale: 'es',
        events: <?php echo json_encode($events); ?>,
        editable: true,
        eventClick: function(info) {
            editEvent(info.event);
        },
        dateClick: function(info) {
            showEventModal(info.date);
        },
        eventDrop: function(info) {
            updateEventDates(info.event);
        },
        eventResize: function(info) {
            updateEventDates(info.event);
        }
    });
    
    calendar.render();
});
</script>

<?php require_once '../../includes/footer.php'; ?> 