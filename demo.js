const openBtn = document.getElementById('vto-open');
const modal = document.getElementById('vto-modal');
const closeBackdrop = document.getElementById('vto-close');
const closeX = document.getElementById('vto-x');

const fileInput = document.getElementById('vto-file');
const img = document.getElementById('vto-img');
const empty = document.getElementById('vto-empty');

const runBtn = document.getElementById('vto-run');
const result = document.getElementById('vto-result');
const beforeImg = document.getElementById('vto-before');
const afterImg = document.getElementById('vto-after');
const dlBtn = document.getElementById('vto-download');

let selfieDataUrl = null;

openBtn.addEventListener('click', () => {
  modal.setAttribute('aria-hidden','false');
});

closeBackdrop.addEventListener('click', () => {
  modal.setAttribute('aria-hidden','true');
});

closeX.addEventListener('click', () => {
  modal.setAttribute('aria-hidden','true');
});

fileInput.addEventListener('change', () => {
  const file = fileInput.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    selfieDataUrl = reader.result;
    img.src = selfieDataUrl;
    img.style.display = 'block';
    empty.style.display = 'none';
  };
  reader.readAsDataURL(file);
});

runBtn.addEventListener('click', () => {
  if (!selfieDataUrl) {
    alert('Carica prima una foto.');
    return;
  }

  // mostra risultato demo
  beforeImg.src = selfieDataUrl;
  afterImg.src = selfieDataUrl;
  beforeImg.style.display = 'block';
  afterImg.style.display = 'block';
  result.style.display = 'block';

  dlBtn.disabled = false;
});

dlBtn.addEventListener('click', () => {
  const a = document.createElement('a');
  a.href = selfieDataUrl;
  a.download = 'demo-prova-virtuale.jpg';
  document.body.appendChild(a);
  a.click();
  a.remove();
});
