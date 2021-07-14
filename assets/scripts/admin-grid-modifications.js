// noinspection JSUnresolvedVariable

async function createSelect() {

  // WordPress has added an inline script prior to this one that includes
  // the aspectRatios variable that we access herein.  therefore, we can use
  // that to construct a <select> element that we then cram into the DOM.

  const select = document.createElement('select');
  select.id = 'media-attachment-ratio-filters';

  let option = document.createElement('option');
  option.text = 'All aspect ratios';
  option.value = 'all';
  select.appendChild(option);

  for (let ratio in aspectRatios) {
    if (aspectRatios.hasOwnProperty(ratio)) {
      option = document.createElement('option');
      option.text = aspectRatios[ratio];
      option.value = ratio;
      select.appendChild(option);
    }
  }

  let sibling = document.getElementById('media-attachment-date-filters');

  while (!sibling) {
    await new Promise(r => setTimeout(r, 1000));
    sibling = document.getElementById('media-attachment-date-filters');
  }

  sibling.parentNode.insertBefore(select, sibling.nextSibling);

  select.addEventListener('change', () => {
    let data = 'action=query-attachments';
    select.parentNode.querySelectorAll('select').forEach((select) => {
      let value = select.id !== 'media-attachment-date-filters'
        ? select.options[select.selectedIndex].value
        : select.options[select.selectedIndex].text

      data += '&' + select.id + '=' + value;
    });

    location.href = location.href.replace(location.search, '?') + data;
  });
}

document.addEventListener('DOMContentLoaded', createSelect);
