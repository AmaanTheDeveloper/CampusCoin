/**
 * Build a synthetic payment-card-like PNG for browser QA upload.
 * Pattern matches CardCandidateValidator L1–L4 expectations.
 */
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function crc32(buf) {
    let c = ~0;
    for (let i = 0; i < buf.length; i++) {
        c ^= buf[i];
        for (let k = 0; k < 8; k++) {
            c = c & 1 ? (0xedb88320 ^ (c >>> 1)) : c >>> 1;
        }
    }
    return ~c >>> 0;
}

function chunk(type, data) {
    const typeBuf = Buffer.from(type);
    const len = Buffer.alloc(4);
    len.writeUInt32BE(data.length, 0);
    const body = Buffer.concat([typeBuf, data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(body), 0);
    return Buffer.concat([len, body, crc]);
}

export function writeQaCardPng(outPath) {
    const W = 640;
    const H = 400;
    // Guide ROI matches CARD_SCAN_CONFIG.roi
    const rx0 = Math.floor(W * 0.12);
    const ry0 = Math.floor(H * 0.22);
    const rw = Math.floor(W * 0.76);
    const rh = Math.floor(H * 0.48);
    const insetX = Math.floor(rw * 0.14);
    const insetY = Math.floor(rh * 0.16);

    const rows = [];
    for (let y = 0; y < H; y++) {
        const row = Buffer.alloc(1 + W * 3);
        row[0] = 0; // filter none
        for (let x = 0; x < W; x++) {
            let g = 22;
            const inRoi = x >= rx0 && x < rx0 + rw && y >= ry0 && y < ry0 + rh;
            if (inRoi) {
                const lx = x - rx0;
                const ly = y - ry0;
                const inside = lx >= insetX && lx < rw - insetX && ly >= insetY && ly < rh - insetY;
                if (inside) {
                    const bx = lx - insetX;
                    const by = ly - insetY;
                    const bw = rw - insetX * 2;
                    const bh = rh - insetY * 2;
                    const relY = by / bh;
                    const relX = bx / bw;
                    const nearEdge = bx < 4 || by < 4 || bx >= bw - 4 || by >= bh - 4;
                    if (nearEdge) {
                        g = bx % 2 === 0 ? 200 : 35;
                    } else if (relY > 0.52 && relY < 0.76) {
                        g = 40 + ((bx * 13) % 160) + (by % 3) * 20;
                    } else if (relY > 0.68 && relY < 0.9 && relX > 0.52) {
                        g = 50 + ((bx * 17 + by * 3) % 140);
                    } else {
                        g = 55 + ((x * 7 + y * 11) % 90);
                    }
                }
            }
            const i = 1 + x * 3;
            row[i] = g;
            row[i + 1] = g;
            row[i + 2] = Math.min(255, g + 8);
        }
        rows.push(row);
    }

    const raw = Buffer.concat(rows);
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(W, 0);
    ihdr.writeUInt32BE(H, 4);
    ihdr[8] = 8;
    ihdr[9] = 2; // RGB
    ihdr[10] = 0;
    ihdr[11] = 0;
    ihdr[12] = 0;

    const png = Buffer.concat([
        Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
        chunk('IHDR', ihdr),
        chunk('IDAT', zlib.deflateSync(raw)),
        chunk('IEND', Buffer.alloc(0)),
    ]);

    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, png);
    return outPath;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const out = path.join(__dirname, 'fixtures', 'qa-card.png');
    writeQaCardPng(out);
    console.log('wrote', out);
}
