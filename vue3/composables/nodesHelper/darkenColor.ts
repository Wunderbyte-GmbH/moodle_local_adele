interface RgbColor {
  r: number;
  g: number;
  b: number;
}

const  darkenColor = (color: string, darken: number): string => {
    let intensity = 0.2;
    let rgb = hexToRgb(color);
    rgb.r = Math.floor(rgb.r * intensity + 128 * darken);
    rgb.g = Math.floor(rgb.g * intensity + 128 * darken);
    rgb.b = Math.floor(rgb.b * intensity + 128 * darken);
    return rgbToHex(rgb.r, rgb.g, rgb.b);
}

const hexToRgb = (hex: string): RgbColor => {
    let bigint = parseInt(hex.slice(1), 16);
    let r = (bigint >> 16) & 255;
    let g = (bigint >> 8) & 255;
    let b = (bigint & 255);
    return { r, g, b };
}

const rgbToHex = (
  r: number,
  g: number,
  b: number
): string => {
    return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
}

export default darkenColor;