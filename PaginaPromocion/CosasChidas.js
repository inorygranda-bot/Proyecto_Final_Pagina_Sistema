// Navegación fluida entre anclas
document.querySelectorAll('nav a').forEach(enlace => {
    enlace.addEventListener('click', function(e) {
        e.preventDefault();
        const destino = document.querySelector(this.getAttribute('href'));
        if (destino) {
            destino.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// Manejo del formulario sin recarga
const formulario = document.getElementById('form-comentarios');
if (formulario) {
    formulario.addEventListener('submit', (e) => {
        e.preventDefault();
        alert('Sugerencia procesada. El equipo técnico revisará tu propuesta.');
        formulario.reset();
    });
}

// Lógica de descarga demo
function descargarDemo() {
    if (confirm("¿Confirmas la descarga del instalador Android?")) {
        alert("Descarga iniciada satisfactoriamente.");
    }
}