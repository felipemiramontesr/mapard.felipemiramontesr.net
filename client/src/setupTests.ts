import '@testing-library/jest-dom';

// Mock scrollIntoView for JSDOM
HTMLElement.prototype.scrollIntoView = function () { };
