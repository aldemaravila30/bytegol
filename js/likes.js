document.addEventListener('DOMContentLoaded', function() {
    // Manejar el envío de likes
    document.querySelectorAll('.like-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const likeBtn = this.querySelector('.like-btn');
            const likeCount = this.querySelector('.like-count');
            const currentCount = parseInt(likeCount.textContent);
            const isLiked = likeBtn.classList.contains('liked');

            // Actualización optimista de la UI
            likeBtn.classList.toggle('liked');
            likeCount.textContent = isLiked ? currentCount - 1 : currentCount + 1;

            fetch('feed.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error('Server error');
                }

                // Actualizar la UI con los datos reales del servidor
                likeCount.textContent = data.likes_count;
                if (data.liked) {
                    likeBtn.classList.add('liked');
                } else {
                    likeBtn.classList.remove('liked');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revertir cambios si hubo un error
                likeBtn.classList.toggle('liked');
                likeCount.textContent = currentCount;
            });
        });
    });

    // Función para mostrar/ocultar el formulario de edición
    window.toggleEditForm = function(publicacionId) {
        const form = document.getElementById('edit-form-' + publicacionId);
        const contenido = document.getElementById('contenido-' + publicacionId);

        if (form.style.display === 'block') {
            form.style.display = 'none';
            contenido.style.display = 'block';
        } else {
            form.style.display = 'block';
            contenido.style.display = 'none';
        }
    }

    // Función para mostrar/ocultar comentarios
    window.toggleComments = function(postId) {
        const commentsSection = document.getElementById('comments-' + postId);
        if (commentsSection.style.display === 'block') {
            commentsSection.style.display = 'none';
        } else {
            commentsSection.style.display = 'block';
        }
    }

    // Función para mostrar/ocultar formulario de edición de comentario
    window.toggleEditCommentForm = function(commentId) {
        const form = document.getElementById('edit-comment-form-' + commentId);
        const text = document.getElementById('comment-text-' + commentId);

        if (form.style.display === 'block') {
            form.style.display = 'none';
            text.style.display = 'block';
        } else {
            form.style.display = 'block';
            text.style.display = 'none';
        }
    }

    // Función para previsualizar imagen antes de subir
    window.previewImage = function(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        const reader = new FileReader();

        reader.onloadend = function() {
            preview.src = reader.result;
            preview.style.display = 'block';
        }

        if (file) {
            reader.readAsDataURL(file);
        } else {
            preview.src = '';
            preview.style.display = 'none';
        }
    }
});
