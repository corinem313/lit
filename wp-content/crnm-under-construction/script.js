const scene = document.querySelector('.scene');

window.addEventListener('pointermove', (event) => {
  const x = (event.clientX / window.innerWidth - 0.5) * 10;
  const y = (event.clientY / window.innerHeight - 0.5) * 10;
  scene.style.transform = `translate(${x}px, ${y}px)`;
});

window.addEventListener('pointerleave', () => {
  scene.style.transform = 'translate(0, 0)';
});

const progress = document.querySelector('.progress i');
progress.addEventListener('animationend', () => {
  progress.style.width = '68%';
});
