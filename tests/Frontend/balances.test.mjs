import test from 'node:test';
import assert from 'node:assert/strict';
import {balanceIssue} from '../../resources/js/balances.js';

test('balances accept the inclusive whole-RWF boundaries', () => {
    for (const value of ['100', '49999', '00100', '1000']) assert.equal(balanceIssue(value, value), null);
});

test('balances reject out-of-range, fractional and non-numeric entries', () => {
    for (const value of ['', '0', '99', '50000', '-100', '100.25', '100.00', 'abc', '1e3', '1,000']) assert.equal(balanceIssue(value), 'format', value);
});

test('balances must be numerically equal without rewriting either input', () => {
    assert.equal(balanceIssue('100', '101'), 'different');
    assert.equal(balanceIssue('49999', '100'), 'different');
    assert.equal(balanceIssue('00100', '100'), null);
    assert.equal(balanceIssue('100', 'invalid'), null);
});
