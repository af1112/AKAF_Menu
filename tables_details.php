<!DOCTYPE html>
<html lang="fa">
<head>
  <meta charset="UTF-8">
  <title>چیدمان رستوران</title>
  <style>
    body {
      margin: 0;
      display: flex;
      font-family: sans-serif;
    }
    #toolbox {
      width: 120px;
      background: #f0f0f0;
      padding: 10px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      border-left: 2px solid #ccc;
    }
    .tool-item {
      width: 100px;
      height: 100px;
      background: white;
      border: 2px dashed #aaa;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: grab;
    }

    #layout {
      flex-grow: 1;
      height: 100vh;
      background: #eaeaea;
      position: relative;
      overflow: hidden;
    }

    .element {
      position: absolute;
      user-select: none;
    }

    .square, .rect, .circle {
      background-color: #fff;
      border: 2px solid #333;
    }
    .square {
      width: 80px;
      height: 80px;
    }
    .rect {
      width: 120px;
      height: 60px;
    }
    .circle {
      width: 80px;
      height: 80px;
      border-radius: 50%;
    }

    .wall-line {
      background: #444;
      height: 4px;
      width: 150px;
    }

    .delete-btn {
      position: absolute;
      top: -10px;
      right: -10px;
      width: 20px;
      height: 20px;
      background: red;
      color: white;
      font-size: 14px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .rotate-btn {
      position: absolute;
      bottom: -10px;
      right: -10px;
      width: 20px;
      height: 20px;
      background: blue;
      color: white;
      font-size: 14px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

  </style>
</head>
<body>

  <div id="layout"></div>

  <div id="toolbox">
    <div class="tool-item" draggable="true" data-type="square">🟥 مربع</div>
    <div class="tool-item" draggable="true" data-type="rectangle">⬛ مستطیل</div>
    <div class="tool-item" draggable="true" data-type="circle">⚪ دایره</div>
    <div class="tool-item" draggable="true" data-type="wall">➖ دیوار</div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
  <script>
    const layout = document.getElementById('layout');
    const toolboxItems = document.querySelectorAll('.tool-item');

    toolboxItems.forEach(item => {
      item.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('type', e.currentTarget.dataset.type);
      });
    });

    layout.addEventListener('dragover', (e) => e.preventDefault());

    layout.addEventListener('drop', (e) => {
      e.preventDefault();
      const type = e.dataTransfer.getData('type');
      const x = e.pageX - layout.offsetLeft;
      const y = e.pageY - layout.offsetTop;

      let el = document.createElement('div');
      el.classList.add('element');

      if (type === 'square') el.classList.add('square');
      else if (type === 'rectangle') el.classList.add('rect');
      else if (type === 'circle') el.classList.add('circle');
      else if (type === 'wall') el.classList.add('wall-line');

      const wrapper = document.createElement('div');
      wrapper.className = 'element';
      wrapper.style.left = x + 'px';
      wrapper.style.top = y + 'px';
      wrapper.appendChild(el);

      const delBtn = document.createElement('div');
      delBtn.className = 'delete-btn';
      delBtn.innerText = '×';
      delBtn.onclick = () => wrapper.remove();
      wrapper.appendChild(delBtn);

      const rotateBtn = document.createElement('div');
      rotateBtn.className = 'rotate-btn';
      rotateBtn.innerText = '↻';
      rotateBtn.onclick = () => {
        const current = parseFloat(wrapper.getAttribute('data-angle')) || 0;
        const newAngle = current + 15;
        wrapper.setAttribute('data-angle', newAngle);

        const x = parseFloat(wrapper.getAttribute('data-x')) || 0;
        const y = parseFloat(wrapper.getAttribute('data-y')) || 0;

        wrapper.style.transform = `translate(${x}px, ${y}px) rotate(${newAngle}deg)`;
      };
      wrapper.appendChild(rotateBtn);

      layout.appendChild(wrapper);
      makeInteractable(wrapper, type);
    });

    function makeInteractable(el, type) {
      interact(el)
        .draggable({
          listeners: {
            move(event) {
              const target = event.target;
              const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
              const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
              const angle = parseFloat(target.getAttribute('data-angle')) || 0;

              target.style.transform = `translate(${x}px, ${y}px) rotate(${angle}deg)`;
              target.setAttribute('data-x', x);
              target.setAttribute('data-y', y);
            }
          }
        });

      if (type === 'wall') {
        interact(el).resizable({
          edges: { left: true, right: true },
          listeners: {
            move(event) {
              const target = event.target.firstChild;
              target.style.width = event.rect.width + 'px';
            }
          }
        });
      }
    }
  </script>
</body>
</html>
