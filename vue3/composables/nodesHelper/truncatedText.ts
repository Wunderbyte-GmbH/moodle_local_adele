
function truncatedText(text: string, size: number | null = null): string {
  if (size == null) {
    size = 38;
  }
  if (text.length > size) {
    return text.substring(0, size) + '...';
  }
  return text;
}

export default truncatedText;