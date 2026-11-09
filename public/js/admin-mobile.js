(() => {
    const button = document.querySelector('.admin-menu-toggle');
    if (button) {
        document.body.classList.add('admin-menu-ready');
        button.setAttribute('aria-expanded', 'false');
        button.addEventListener('click', () => {
            const open = document.body.classList.toggle('admin-menu-open');
            button.setAttribute('aria-expanded', String(open));
            button.textContent = open ? 'Закрыть меню' : 'Меню';
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && document.body.classList.contains('admin-menu-open')) {
                document.body.classList.remove('admin-menu-open');
                button.setAttribute('aria-expanded', 'false');
                button.textContent = 'Меню';
                button.focus();
            }
        });
    }
    document.querySelectorAll('.main .table-wrap table').forEach(table => {
        const headers = Array.from(table.querySelectorAll('thead tr:first-child th'), th => th.textContent.trim());
        if (!headers.length) return;
        const prepareRow = row => {
            Array.from(row.cells).forEach((cell, index) => {
                cell.dataset.label = headers[index] || 'Действия';
                if (cell.colSpan === 1) {
                    const content = document.createElement('div');
                    content.className = 'mobile-cell-content';
                    while (cell.firstChild) content.appendChild(cell.firstChild);
                    cell.appendChild(content);
                }
            });
        };
        table.querySelectorAll('tbody tr').forEach(prepareRow);
        table.querySelectorAll('tbody').forEach(body => {
            new MutationObserver(records => records.forEach(record => {
                record.addedNodes.forEach(node => {
                    if (node.nodeType === 1 && node.tagName === 'TR') prepareRow(node);
                });
            })).observe(body, {childList:true});
        });
        table.classList.add('mobile-cards');
    });
})();
