(() => {
    const config = window.agendaGerencialConfig || {};
    const captureTarget = document.getElementById(config.captureTargetId || 'agendaGerencialCapture');
    const notice = document.getElementById(config.noticeId || 'agendaGerencialNotice');
    const buttons = {
        print: document.querySelector('[data-report-action="print"]'),
        pdf: document.querySelector('[data-report-action="pdf"]'),
        jpg: document.querySelector('[data-report-action="jpg"]'),
        whatsappPdf: document.querySelector('[data-report-action="whatsapp-pdf"]'),
        whatsappJpg: document.querySelector('[data-report-action="whatsapp-jpg"]'),
    };

    function showNotice(message, type = 'info') {
        if (!notice) {
            return;
        }

        const allowedTypes = ['success', 'warning', 'danger', 'info'];
        const noticeType = allowedTypes.includes(type) ? type : 'info';
        notice.textContent = message;
        notice.className = `agenda-gerencial-notice is-${noticeType}`;
        notice.hidden = false;

        window.clearTimeout(showNotice.timeoutId);
        showNotice.timeoutId = window.setTimeout(() => {
            notice.hidden = true;
        }, 3600);
    }

    function setBusy(isBusy) {
        Object.values(buttons).forEach((button) => {
            if (button) {
                button.disabled = isBusy;
            }
        });
    }

    function reportFileName(extension) {
        const baseName = String(config.fileBaseName || 'relatorio-gerencial-agenda').trim() || 'relatorio-gerencial-agenda';
        return `${baseName}.${extension}`;
    }

    async function buildCanvas() {
        if (!captureTarget || typeof window.html2canvas !== 'function') {
            showNotice('Nao foi possivel montar a imagem do relatorio.', 'danger');
            return null;
        }

        return window.html2canvas(captureTarget, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            width: captureTarget.scrollWidth,
            height: captureTarget.scrollHeight,
            windowWidth: Math.max(captureTarget.scrollWidth, document.documentElement.clientWidth),
            windowHeight: Math.max(captureTarget.scrollHeight, document.documentElement.clientHeight),
            scrollX: 0,
            scrollY: 0,
        });
    }

    function downloadUrl(url, fileName) {
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        link.click();
    }

    function downloadCanvasAsJpg(canvas) {
        if (!canvas) {
            return;
        }

        downloadUrl(canvas.toDataURL('image/jpeg', 0.96), reportFileName('jpg'));
    }

    function buildPdf(canvas) {
        if (!canvas || !window.jspdf?.jsPDF) {
            return null;
        }

        const { jsPDF } = window.jspdf;
        const orientation = canvas.width >= canvas.height ? 'landscape' : 'portrait';
        const pdf = new jsPDF(orientation, 'mm', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const maxWidth = pageWidth - 12;
        const maxHeight = pageHeight - 12;
        const scale = Math.min(maxWidth / canvas.width, maxHeight / canvas.height);
        const renderWidth = canvas.width * scale;
        const renderHeight = canvas.height * scale;

        pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 6, 6, renderWidth, renderHeight);

        return pdf;
    }

    function canvasToJpgFile(canvas) {
        return new Promise((resolve) => {
            if (!canvas || typeof File !== 'function') {
                resolve(null);
                return;
            }

            canvas.toBlob((blob) => {
                resolve(blob ? new File([blob], reportFileName('jpg'), { type: 'image/jpeg' }) : null);
            }, 'image/jpeg', 0.96);
        });
    }

    function pdfToFile(pdf) {
        if (!pdf || typeof File !== 'function') {
            return null;
        }

        const blob = pdf.output('blob');

        return blob ? new File([blob], reportFileName('pdf'), { type: 'application/pdf' }) : null;
    }

    async function tryShareFile(file, title, text) {
        if (!file || !navigator.share || !navigator.canShare?.({ files: [file] })) {
            return false;
        }

        await navigator.share({
            files: [file],
            title,
            text,
        });

        return true;
    }

    function openWhatsappFallback(downloadCallback, typeLabel, fallbackWindow = null) {
        downloadCallback();
        const whatsappUrl = `https://web.whatsapp.com/send?text=${encodeURIComponent(String(config.whatsappShareText || ''))}`;

        if (fallbackWindow) {
            fallbackWindow.location.href = whatsappUrl;
        } else {
            window.open(whatsappUrl, '_blank');
        }

        showNotice(`${typeLabel} baixado. Anexe o arquivo no WhatsApp para concluir o envio.`, 'info');
    }

    async function runAction(action) {
        setBusy(true);

        try {
            await action();
        } catch (error) {
            if (error && error.name === 'AbortError') {
                showNotice('Compartilhamento cancelado.', 'warning');
            } else {
                showNotice('Nao foi possivel concluir esta acao.', 'danger');
            }
        } finally {
            setBusy(false);
        }
    }

    async function printReport() {
        const printWindow = window.open('', '_blank', 'width=1280,height=900');

        if (!printWindow) {
            showNotice('Permita a abertura de janelas para imprimir o relatorio.', 'warning');
            return;
        }

        printWindow.document.write(`
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Preparando impressao</title>
                <style>
                    body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Arial, sans-serif; color: #0f4c5c; }
                </style>
            </head>
            <body>Preparando impressao...</body>
            </html>
        `);
        printWindow.document.close();

        const canvas = await buildCanvas();

        if (!canvas) {
            printWindow.close();
            return;
        }

        const imageUrl = canvas.toDataURL('image/jpeg', 0.96);

        printWindow.document.open();
        printWindow.document.write(`
            <html>
            <head>
                <meta charset="UTF-8">
                <title>${String(config.reportTitle || 'Relatorio gerencial')}</title>
                <style>
                    @page { size: landscape; margin: 8mm; }
                    html, body { margin: 0; background: #fff; }
                    body { padding: 8mm; }
                    img { display: block; margin: 0 auto; max-width: 100%; height: auto; }
                </style>
            </head>
            <body>
                <img src="${imageUrl}" alt="Relatorio gerencial" onload="window.focus(); setTimeout(function(){ window.print(); }, 150);">
            </body>
            </html>
        `);
        printWindow.document.close();
        showNotice('Relatorio enviado para impressao.', 'success');
    }

    async function exportPdf() {
        const canvas = await buildCanvas();
        const pdf = buildPdf(canvas);

        if (!pdf) {
            showNotice('Nao foi possivel gerar o PDF do relatorio.', 'danger');
            return;
        }

        pdf.save(reportFileName('pdf'));
        showNotice('PDF do relatorio baixado.', 'success');
    }

    async function exportJpg() {
        const canvas = await buildCanvas();

        if (!canvas) {
            return;
        }

        downloadCanvasAsJpg(canvas);
        showNotice('JPG do relatorio baixado.', 'success');
    }

    async function sharePdfToWhatsapp() {
        const canNativeShare = Boolean(navigator.share && navigator.canShare && typeof File === 'function');
        const fallbackWindow = canNativeShare ? null : window.open('', '_blank');
        const canvas = await buildCanvas();
        const pdf = buildPdf(canvas);

        if (!pdf) {
            fallbackWindow?.close();
            showNotice('Nao foi possivel gerar o PDF do relatorio.', 'danger');
            return;
        }

        const file = pdfToFile(pdf);

        if (await tryShareFile(file, String(config.reportTitle || 'Relatorio gerencial'), String(config.whatsappShareText || ''))) {
            fallbackWindow?.close();
            showNotice('PDF compartilhado.', 'success');
            return;
        }

        openWhatsappFallback(() => pdf.save(reportFileName('pdf')), 'PDF', fallbackWindow);
    }

    async function shareJpgToWhatsapp() {
        const canNativeShare = Boolean(navigator.share && navigator.canShare && typeof File === 'function');
        const fallbackWindow = canNativeShare ? null : window.open('', '_blank');
        const canvas = await buildCanvas();

        if (!canvas) {
            fallbackWindow?.close();
            return;
        }

        const file = await canvasToJpgFile(canvas);

        if (await tryShareFile(file, String(config.reportTitle || 'Relatorio gerencial'), String(config.whatsappShareText || ''))) {
            fallbackWindow?.close();
            showNotice('JPG compartilhado.', 'success');
            return;
        }

        openWhatsappFallback(() => downloadCanvasAsJpg(canvas), 'JPG', fallbackWindow);
    }

    if (buttons.print) {
        buttons.print.addEventListener('click', () => runAction(printReport));
    }

    if (buttons.pdf) {
        buttons.pdf.addEventListener('click', () => runAction(exportPdf));
    }

    if (buttons.jpg) {
        buttons.jpg.addEventListener('click', () => runAction(exportJpg));
    }

    if (buttons.whatsappPdf) {
        buttons.whatsappPdf.addEventListener('click', () => runAction(sharePdfToWhatsapp));
    }

    if (buttons.whatsappJpg) {
        buttons.whatsappJpg.addEventListener('click', () => runAction(shareJpgToWhatsapp));
    }
})();
