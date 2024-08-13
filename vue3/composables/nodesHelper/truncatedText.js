// truncated Text
function truncatedText(text) {
  const maxTextLength = 10;
  if (text.length > maxTextLength) {
    return text.substring(0, maxTextLength) + '...';
  }
  return text;
}

export default truncatedText;