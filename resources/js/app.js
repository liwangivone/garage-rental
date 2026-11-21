// Toggle sidebar di layar kecil
document.querySelectorAll('[data-sidebar-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.toggle('-translate-x-full');
        document.getElementById('sidebar-backdrop')?.classList.toggle('hidden');
    });
});
