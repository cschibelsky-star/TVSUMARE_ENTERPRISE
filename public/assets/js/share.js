(() => {
  async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    }
  }

  async function shareArticle(button) {
    const title = button.dataset.title || document.title;
    const text = button.dataset.text || '';
    const url = button.dataset.url || window.location.href;

    if (navigator.share) {
      try {
        await navigator.share({ title, text, url });
        return;
      } catch (error) {
        if (error && error.name === 'AbortError') return;
      }
    }

    await copyText(`${title}\n${text}\n${url}`.trim());
    const original = button.textContent;
    button.textContent = 'Link copiado';
    setTimeout(() => button.textContent = original, 2200);
  }

  async function shareInstagram(button) {
    const title = button.dataset.title || document.title;
    const url = button.dataset.url || window.location.href;
    const caption = button.dataset.caption || `${title}\n\nLeia na TV Sumaré: ${url}\n\n#TVSumare #Sumare #Noticias`;

    await copyText(caption);

    if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent) && navigator.share) {
      try {
        await navigator.share({ title, text: caption, url });
        return;
      } catch (error) {
        if (error && error.name === 'AbortError') return;
      }
    }

    const original = button.textContent;
    button.textContent = 'Legenda copiada';
    setTimeout(() => button.textContent = original, 2400);
  }

  document.addEventListener('click', (event) => {
    const shareButton = event.target.closest('[data-share-article]');
    if (shareButton) {
      event.preventDefault();
      shareArticle(shareButton);
      return;
    }

    const instagramButton = event.target.closest('[data-share-instagram]');
    if (instagramButton) {
      event.preventDefault();
      shareInstagram(instagramButton);
    }
  });
})();
