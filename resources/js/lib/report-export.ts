import type { ReportData } from '@/types/report';
import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';

function sanitizeFilenamePart(value: string): string {
    return value
        .replace(/[^a-zA-Z0-9-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

export function reportExportFilename(report: ReportData, extension: 'pdf' | 'png'): string {
    const from = sanitizeFilenamePart(report.period.from);
    const to = sanitizeFilenamePart(report.period.to);
    const range = from === to || report.period.from.slice(0, 7) === report.period.to.slice(0, 7) ? from.slice(0, 7) || from : `${from}-to-${to}`;

    return `campus-coin-report-${range}.${extension}`;
}

/**
 * Wait until export surface has layout and Recharts containers have non-zero size.
 * Avoids capturing blank SVGs from off-screen ResponsiveContainers.
 */
export async function waitForReportExportReady(element: HTMLElement, timeoutMs = 2500): Promise<void> {
    if (typeof document !== 'undefined' && document.fonts?.ready) {
        try {
            await document.fonts.ready;
        } catch {
            // Font readiness is best-effort.
        }
    }

    await new Promise<void>((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
    });

    const started = performance.now();

    while (performance.now() - started < timeoutMs) {
        const width = element.scrollWidth;
        const height = element.scrollHeight;
        const containers = element.querySelectorAll<HTMLElement>('.recharts-responsive-container, .recharts-wrapper');
        const chartsReady = containers.length === 0 || Array.from(containers).every((node) => node.clientWidth > 0 && node.clientHeight > 0);
        const chartSlots = element.querySelectorAll('[role="img"]').length;
        const svgCount = element.querySelectorAll('svg.recharts-surface').length;
        const chartsPainted = chartSlots === 0 || svgCount > 0;

        if (width > 0 && height > 0 && chartsReady && chartsPainted) {
            await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
            return;
        }

        await new Promise<void>((resolve) => setTimeout(resolve, 50));
    }
}

/**
 * html2canvas cannot parse modern CSS color functions (oklch/lab) used by Tailwind v4.
 * Strip stylesheets from the clone and rely on the export document's inline hex styles.
 */
function prepareCloneForCapture(clonedDocument: Document, clonedElement: HTMLElement, sourceWidth: number): void {
    clonedDocument.querySelectorAll('style, link[rel="stylesheet"]').forEach((node) => {
        node.remove();
    });

    clonedElement.style.backgroundColor = '#ffffff';
    clonedElement.style.color = '#0f172a';
    clonedElement.style.opacity = '1';
    clonedElement.style.position = 'static';
    clonedElement.style.left = '0';
    clonedElement.style.top = '0';
    clonedElement.style.transform = 'none';
    clonedElement.style.width = `${Math.max(sourceWidth, 900)}px`;
    clonedElement.style.fontFamily = 'Segoe UI, Helvetica, Arial, sans-serif';

    // Ensure chart SVG strokes/fills remain visible without stylesheets.
    clonedElement.querySelectorAll('svg').forEach((svg) => {
        const el = svg as SVGElement;
        el.style.maxWidth = '100%';
        el.style.overflow = 'visible';
    });
}

async function captureReportElement(element: HTMLElement): Promise<HTMLCanvasElement> {
    await waitForReportExportReady(element);

    const width = Math.max(element.scrollWidth, 900);
    const height = Math.max(element.scrollHeight, 1);

    try {
        return await html2canvas(element, {
            scale: 2,
            useCORS: true,
            allowTaint: false,
            backgroundColor: '#ffffff',
            logging: false,
            width,
            height,
            windowWidth: width,
            windowHeight: height,
            scrollX: 0,
            scrollY: 0,
            onclone: (clonedDocument, clonedElement) => {
                prepareCloneForCapture(clonedDocument, clonedElement, width);
            },
        });
    } catch (error) {
        // Surface a stable message; callers map to the user-facing export error.
        throw error instanceof Error ? error : new Error('Could not capture report.');
    }
}

function triggerDownload(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export async function exportReportAsImage(element: HTMLElement, report: ReportData): Promise<void> {
    const canvas = await captureReportElement(element);

    try {
        await new Promise<void>((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (!blob || blob.size === 0) {
                    reject(new Error('Could not create image.'));
                    return;
                }
                triggerDownload(blob, reportExportFilename(report, 'png'));
                resolve();
            }, 'image/png');
        });
    } finally {
        canvas.width = 0;
        canvas.height = 0;
    }
}

export async function exportReportAsPdf(element: HTMLElement, report: ReportData): Promise<void> {
    const canvas = await captureReportElement(element);

    try {
        const imgData = canvas.toDataURL('image/png');
        if (!imgData || imgData === 'data:,') {
            throw new Error('Could not create PDF image.');
        }

        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4',
        });

        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 10;
        const usableWidth = pageWidth - margin * 2;
        const usableHeight = pageHeight - margin * 2;

        const imgWidth = usableWidth;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        let heightLeft = imgHeight;
        let position = margin;

        pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight, undefined, 'FAST');
        heightLeft -= usableHeight;

        while (heightLeft > 1) {
            position = margin - (imgHeight - heightLeft);
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight, undefined, 'FAST');
            heightLeft -= usableHeight;
        }

        pdf.save(reportExportFilename(report, 'pdf'));
    } finally {
        canvas.width = 0;
        canvas.height = 0;
    }
}
