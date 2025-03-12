let currentEvent = null;

function showEventModal(date = null) {
    const modal = document.getElementById('event-modal');
    const form = document.getElementById('event-form');
    
    // Limpiar formulario
    form.reset();
    document.getElementById('event-id').value = '';
    
    // Si se proporciona una fecha, establecerla
    if (date) {
        const startInput = document.getElementById('event-start');
        const endInput = document.getElementById('event-end');
        
        startInput.value = date.toISOString().slice(0, 16);
        endInput.value = new Date(date.getTime() + 3600000).toISOString().slice(0, 16);
    }
    
    modal.style.display = 'block';
}

function closeEventModal() {
    document.getElementById('event-modal').style.display = 'none';
    currentEvent = null;
}

function editEvent(event) {
    currentEvent = event;
    
    document.getElementById('event-id').value = event.id;
    document.getElementById('event-title').value = event.title;
    document.getElementById('event-description').value = event.extendedProps.description || '';
    document.getElementById('event-start').value = event.start.toISOString().slice(0, 16);
    document.getElementById('event-end').value = event.end.toISOString().slice(0, 16);
    document.getElementById('event-type').value = event.extendedProps.type;
    document.getElementById('event-color').value = event.backgroundColor;
    
    if (event.extendedProps.course_id) {
        document.getElementById('event-course').value = event.extendedProps.course_id;
    }
    
    showEventModal();
}

function updateEventDates(event) {
    fetch('/api/calendar/update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: event.id,
            start: event.start.toISOString(),
            end: event.end.toISOString()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Error al actualizar el evento');
            calendar.refetchEvents();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        calendar.refetchEvents();
    });
}

document.getElementById('event-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        id: document.getElementById('event-id').value,
        title: document.getElementById('event-title').value,
        description: document.getElementById('event-description').value,
        start: document.getElementById('event-start').value,
        end: document.getElementById('event-end').value,
        type: document.getElementById('event-type').value,
        color: document.getElementById('event-color').value,
        course_id: document.getElementById('event-course').value
    };
    
    const url = formData.id ? '/api/calendar/update.php' : '/api/calendar/create.php';
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            calendar.refetchEvents();
            closeEventModal();
        } else {
            alert(data.error || 'Error al guardar el evento');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar el evento');
    });
});

function filterEvents() {
    const type = document.getElementById('event-type-filter').value;
    calendar.refetchEvents();
} 