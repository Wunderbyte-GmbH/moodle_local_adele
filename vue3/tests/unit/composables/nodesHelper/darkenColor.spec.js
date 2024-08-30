import darkenColor from '../../../../composables/nodesHelper/darkenColor';

describe('darkenColor', () => {
  it('should darken the color', () => {
    const color = '#FF5733'; // A shade of orange
    const darken = 0.5; // A value to darken the color
    const expectedOutput = '#73514A'; // Expected darker color

    expect(darkenColor(color, darken)).toBe(expectedOutput);
  });
});