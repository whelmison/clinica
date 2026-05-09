(function () {
    const loadedScripts = {};

    function loadScript(src) {
        if (loadedScripts[src]) {
            return loadedScripts[src];
        }

        loadedScripts[src] = new Promise((resolve, reject) => {
            const existing = document.querySelector('script[src="' + src + '"]');

            if (existing) {
                existing.addEventListener('load', resolve, { once: true });
                existing.addEventListener('error', reject, { once: true });
                if (existing.dataset.loaded === '1') {
                    resolve();
                }
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = () => {
                script.dataset.loaded = '1';
                resolve();
            };
            script.onerror = () => reject(new Error('Nao foi possivel carregar a biblioteca de exportacao.'));
            document.head.appendChild(script);
        });

        return loadedScripts[src];
    }

    function slug(value) {
        return String(value || 'lista')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'lista';
    }

    function downloadDataUrl(dataUrl, filename) {
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    function setButtonsDisabled(disabled) {
        document.querySelectorAll('[data-export-list]').forEach((button) => {
            button.disabled = disabled;
        });
    }

    function addCanvasSliceToPdf(pdf, sourceCanvas, y, sliceHeight, pageWidth, pageHeight, margin) {
        const sliceCanvas = document.createElement('canvas');
        const sliceContext = sliceCanvas.getContext('2d');
        sliceCanvas.width = sourceCanvas.width;
        sliceCanvas.height = sliceHeight;
        sliceContext.fillStyle = '#ffffff';
        sliceContext.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
        sliceContext.drawImage(
            sourceCanvas,
            0,
            y,
            sourceCanvas.width,
            sliceHeight,
            0,
            0,
            sourceCanvas.width,
            sliceHeight
        );

        const availableWidth = pageWidth - margin * 2;
        const availableHeight = pageHeight - margin * 2;
        const imageHeight = Math.min(availableHeight, sliceHeight * availableWidth / sourceCanvas.width);
        pdf.addImage(sliceCanvas.toDataURL('image/jpeg', 0.92), 'JPEG', margin, margin, availableWidth, imageHeight);
    }

    window.appExportList = async function appExportList(targetId, type, filenameBase) {
        const target = document.getElementById(targetId);

        if (!target) {
            alert('Lista nao encontrada para exportacao.');
            return;
        }

        if (target.innerText.trim() === '') {
            alert('Nao ha dados visiveis para exportar.');
            return;
        }

        setButtonsDisabled(true);

        try {
            await loadScript('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js');
            const canvas = await window.html2canvas(target, {
                backgroundColor: '#ffffff',
                scale: Math.min(2, window.devicePixelRatio || 1.5),
                useCORS: true,
            });
            const baseName = slug(filenameBase);

            if (type === 'jpg') {
                downloadDataUrl(canvas.toDataURL('image/jpeg', 0.92), baseName + '.jpg');
                return;
            }

            await loadScript('https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js');
            const pdf = new window.jspdf.jsPDF('landscape', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const margin = 8;
            const availableWidth = pageWidth - margin * 2;
            const availableHeight = pageHeight - margin * 2;
            const maxSliceHeight = Math.floor(canvas.width * availableHeight / availableWidth);
            let y = 0;

            while (y < canvas.height) {
                if (y > 0) {
                    pdf.addPage();
                }

                const sliceHeight = Math.min(maxSliceHeight, canvas.height - y);
                addCanvasSliceToPdf(pdf, canvas, y, sliceHeight, pageWidth, pageHeight, margin);
                y += sliceHeight;
            }

            pdf.save(baseName + '.pdf');
        } catch (error) {
            alert(error.message || 'Nao foi possivel exportar a lista.');
        } finally {
            setButtonsDisabled(false);
        }
    };
}());
