const formulario = document.getElementById('form-comentarios');

if (formulario) {
    formulario.addEventListener('submit', (e) => {
        e.preventDefault();
        alert('Sugerencia procesada.');
        formulario.reset();
    });
}
