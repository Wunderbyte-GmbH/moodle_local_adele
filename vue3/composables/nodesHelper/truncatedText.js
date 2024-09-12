// truncated Text
function truncatedText(text, size=null) {
  if (size == null) {
    size = 38;
  }
  if (text.length > size) {
    return text.substring(0, size) + '...';
  }
  return text;
}

export default truncatedText;