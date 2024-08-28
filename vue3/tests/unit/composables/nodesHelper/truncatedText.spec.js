import truncatedText from '../../../../composables/nodesHelper/truncatedText';

describe('truncatedText', () => {
  const short_text  = 'Testing Title';
  const exact_text = 'A very very long Testing Tit';
  const long_text = 'A very very long Testing Title must be cut smaller';
  const long_text_shorten = 'A very very long Testing Tit...';
  it('should return the text as it is because it is too short', () => {
    const result = truncatedText(short_text);
    expect(result).toEqual(short_text);
  });
  it('should return the text as it is because it is excat the lenght', () => {
    const result = truncatedText(exact_text);
    expect(result).toEqual(exact_text);
  });
  it('should return the text truncated', () => {
    const result = truncatedText(long_text);
    expect(result).toEqual(long_text_shorten);
  });
});