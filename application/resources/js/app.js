document.addEventListener('submit', event => {
    const button = event.submitter;
    if (!button) return;
    setTimeout(() => { button.disabled = true; button.textContent = 'Memproses…'; }, 0);
});
