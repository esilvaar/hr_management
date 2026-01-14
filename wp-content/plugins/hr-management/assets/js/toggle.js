document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnToggle');
    const collapse = document.getElementById('collapseMasDatos');

    if (!btn || !collapse) return;

    collapse.addEventListener('shown.bs.collapse', () => {
        btn.textContent = 'Mostrar menos datos';
    });

    collapse.addEventListener('hidden.bs.collapse', () => {
        btn.textContent = 'Mostrar más datos';
    });
});
