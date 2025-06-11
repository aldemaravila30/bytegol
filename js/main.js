// main.js

document.addEventListener('DOMContentLoaded', () => {

    // --- 1. Manejo de Dropdowns de Opciones (Editar/Eliminar) ---
    // Delegación de eventos para los botones de toggle de opciones (posts y comentarios)
    document.body.addEventListener('click', (event) => {
        const toggleButton = event.target.closest('.options-toggle-btn');
        const clickedInsideDropdown = event.target.closest('.options-dropdown-content');

        // Cerrar todos los demás dropdowns abiertos si el clic no fue en un toggle o dentro de un dropdown abierto
        if (!toggleButton && !clickedInsideDropdown) {
            console.log('Clic fuera de cualquier botón de toggle y dropdown. Cerrando todos los dropdowns.');
            document.querySelectorAll('.options-dropdown-content').forEach(dropdown => {
                dropdown.style.display = 'none';
            });
        }

        // Si se hizo clic en un botón de toggle
        if (toggleButton) {
            event.stopPropagation(); // Evitar que el clic en el botón se propague y cierre el dropdown inmediatamente
            console.log('Clic en botón de toggle:', toggleButton);

            // Obtener el ID y tipo del elemento (post o comentario)
            const itemId = toggleButton.dataset.id;
            const itemType = toggleButton.dataset.type;
            const dropdownId = `<span class="math-inline">\{itemType\}\-dropdown\-</span>{itemId}`; // Construir el ID del dropdown esperado

            const dropdownContent = document.getElementById(dropdownId); // Buscar por ID para mayor robustez

            if (!dropdownContent) {
                console.error(`Error: Dropdown content con ID '${dropdownId}' no encontrado para el botón:`, toggleButton);
                return; // Salir si no se encuentra el dropdown
            }

            console.log('Dropdown content encontrado:', dropdownContent);

            // Cerrar todos los demás dropdowns abiertos
            document.querySelectorAll('.options-dropdown-content').forEach(otherDropdown => {
                if (otherDropdown !== dropdownContent && otherDropdown.style.display === 'block') {
                    otherDropdown.style.display = 'none';
                    console.log('Cerrando otro dropdown:', otherDropdown.id);
                }
            });

            // Alternar la visibilidad del dropdown actual
            const isCurrentlyVisible = dropdownContent.style.display === 'block';
            dropdownContent.style.display = isCurrentlyVisible ? 'none' : 'block';
            console.log(`Dropdown con ID '${dropdownId}' ahora está: ${dropdownContent.style.display}`);
        }
    });


    // --- 2. Manejo del Modal de Confirmación para Eliminar (Posts y Comentarios) ---
    // Delegación de eventos para los botones de eliminar (abrir modal)
    document.body.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('.delete-btn');

        if (deleteButton) {
            event.preventDefault(); // Prevenir el comportamiento por defecto del botón
            event.stopPropagation(); // Evitar que el clic se propague al body y cierre dropdowns

            const itemId = deleteButton.dataset.id;
            const itemType = deleteButton.dataset.type; // 'publicacion' o 'comentario'

            console.log(`Intento de eliminar: ID=<span class="math-inline">\{itemId\}, Tipo\=</span>{itemType}`);

            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const deleteModal = document.getElementById('deleteConfirmationModal');
            const deleteMessage = document.getElementById('deleteMessage');

            // Configurar el mensaje del modal
            deleteMessage.textContent = `¿Estás seguro de que quieres eliminar este ${itemType}? Esta acción no se puede deshacer.`;

            // Establecer los data-attributes en el botón de confirmación del modal
            confirmDeleteBtn.dataset.id = itemId;
            confirmDeleteBtn.dataset.type = itemType;

            // Mostrar el modal
            deleteModal.style.display = 'block';

            // Ocultar el dropdown de opciones si está abierto
            const currentDropdown = deleteButton.closest('.options-dropdown-content');
            if (currentDropdown) {
                currentDropdown.style.display = 'none';
            }
        }
    });

    // Manejo de la confirmación de eliminación
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        const itemId = this.dataset.id;
        const itemType = this.dataset.type;

        console.log(`Confirmada eliminación de: ID=<span class="math-inline">\{itemId\}, Tipo\=</span>{itemType}`);

        // Redirigir al script de eliminación correspondiente o enviar AJAX
        if (itemType === 'publicacion') {
            // Crear un formulario temporal para enviar la solicitud POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'feed.php'; // Apunta a feed.php para procesar la eliminación de publicaciones

            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'publicacion_id';
            inputId.value = itemId;
            form.appendChild(inputId);

            const inputSubmit = document.createElement('input');
            inputSubmit.type = 'hidden';
            inputSubmit.name = 'eliminar_publicacion'; // Nombre para el POST en PHP
            inputSubmit.value = '1';
            form.appendChild(inputSubmit);

            document.body.appendChild(form);
            form.submit();
        } else if (itemType === 'comentario') {
            // Enviar solicitud AJAX para eliminar comentario
            // Asumiendo que tienes un endpoint para eliminar comentarios
            fetch('comentarios.php', { // Apunta a comentarios.php
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `eliminar_comentario=1&comentario_id=${itemId}`
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Comentario eliminado con éxito.');
                    // Opcional: Eliminar el comentario del DOM sin recargar la página
                    const commentElement = document.querySelector(`.comment[data-comment-id="${itemId}"]`);
                    if (commentElement) {
                        commentElement.remove();
                    }
                } else {
                    alert(`Error al eliminar comentario: ${result.message || 'Error desconocido'}`);
                }
            })
            .catch(error => {
                console.error('Error al eliminar comentario:', error);
                alert('Ocurrió un error de red o del servidor al eliminar el comentario.');
            });
        }

        // Ocultar el modal después de la acción
        document.getElementById('deleteConfirmationModal').style.display = 'none';
    });

    // Manejo de la cancelación de eliminación
    document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
        document.getElementById('deleteConfirmationModal').style.display = 'none';
        console.log('Eliminación cancelada.');
    });

    // Cierre del modal al hacer clic fuera de él (opcional)
    window.addEventListener('click', function(event) {
        const deleteModal = document.getElementById('deleteConfirmationModal');
        if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
            console.log('Modal cerrado al hacer clic fuera.');
        }
    });

    // --- 3. Manejo de Edición de Comentarios (mostrar/ocultar formulario) ---
    // Delegación de eventos para los botones de editar comentarios
    document.body.addEventListener('click', (event) => {
        const editButton = event.target.closest('button[data-action="edit"][data-type="comment"]');

        if (editButton) {
            event.preventDefault();
            event.stopPropagation(); // Evitar que el clic se propague al body y cierre dropdowns

            const commentId = editButton.dataset.id;
            console.log('Clic en botón de editar comentario. ID:', commentId);

            const commentTextElement = document.getElementById(`comment-text-${commentId}`);
            const editFormElement = document.getElementById(`edit-comment-form-${commentId}`);
            const currentDropdown = editButton.closest('.options-dropdown-content');

            if (commentTextElement && editFormElement) {
                // Ocultar el texto y mostrar el formulario
                commentTextElement.style.display = 'none';
                editFormElement.style.display = 'block';

                // Autofocus en el textarea y ajustar altura
                const textarea = editFormElement.querySelector('textarea');
                if (textarea) {
                    textarea.focus();
                    textarea.style.height = 'auto';
                    textarea.style.height = (textarea.scrollHeight) + 'px';
                }

                // Cerrar el dropdown de opciones después de abrir el formulario de edición
                if (currentDropdown) {
                    currentDropdown.style.display = 'none';
                }
            } else {
                console.error(`Elementos de comentario/formulario de edición no encontrados para ID: ${commentId}`);
            }
        }
    });

    // Manejo de la cancelación de edición de comentario
    document.body.addEventListener('click', (event) => {
        const cancelEditButton = event.target.closest('button[data-action="cancel-edit-comment"]');
        if (cancelEditButton) {
            event.preventDefault();
            event.stopPropagation(); // Prevenir propagación

            const commentId = cancelEditButton.dataset.id;
            console.log('Clic en botón de cancelar edición de comentario. ID:', commentId);

            const commentTextElement = document.getElementById(`comment-text-${commentId}`);
            const editFormElement = document.getElementById(`edit-comment-form-${commentId}`);

            if (commentTextElement && editFormElement) {
                commentTextElement.style.display = 'block'; // Mostrar texto original
                editFormElement.style.display = 'none'; // Ocultar formulario de edición
            } else {
                console.error(`Elementos de comentario/formulario de edición no encontrados para ID: ${commentId}`);
            }
        }
    });

    // Manejo del envío de edición de comentario
    document.body.addEventListener('submit', async (event) => {
        const editCommentForm = event.target.closest('.edit-comment-form');

        if (editCommentForm) {
            event.preventDefault(); // Prevenir el envío normal del formulario

            const commentId = editCommentForm.dataset.id;
            const newContent = editCommentForm.querySelector('textarea[name="edited_content"]').value.trim();

            console.log(`Intento de guardar edición de comentario. ID: ${commentId}, Contenido