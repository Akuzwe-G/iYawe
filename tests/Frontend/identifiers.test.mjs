import test from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {normaliseCard, cardIssue, identificationIssue} from '../../resources/js/identifiers.js';
const php=spawnSync('php',['-r',"require 'vendor/autoload.php'; echo json_encode(App\\Support\\CommuterIdentifiers::specifications());"],{encoding:'utf8'});
assert.equal(php.status,0,php.stderr);
const specifications=JSON.parse(php.stdout);
test('card format accepts lower case and preserves leading zeroes',()=>{
 for(const value of ['00000000','00ab12cd','ABCDEF01','12345678'])assert.equal(cardIssue(value),null);
 assert.equal(normaliseCard('00ab12cd'),'00AB12CD');
});
test('card format rejects short, long, nonhex, separators and same-card variants',()=>{
 for(const value of ['1234567','123456789','00GH1234','00AB 123','00AB-123','０１２３４５６７'])assert.equal(cardIssue(value),'format');
 assert.equal(cardIssue('00ab1234','00AB1234'),'different');
});
test('Rwandan ID frontend matches the 15/16/17 digit backend boundaries',()=>{
 assert.equal(identificationIssue('123456789012345','rwandan_national_id',specifications),'format');
 assert.equal(identificationIssue('0000000000000000','rwandan_national_id',specifications),null);
 assert.equal(identificationIssue('12345678901234567','rwandan_national_id',specifications),'format');
 assert.equal(identificationIssue('123456789012345X','rwandan_national_id',specifications),'format');
});
test('passport accepts internal hyphens and rejects spaces and edge hyphens',()=>{
 for(const value of ['Ab-01','A--01','A'.repeat(20)])assert.equal(identificationIssue(value,'passport',specifications),null);
 for(const value of ['AB12','A'.repeat(21),'AB 123','-AB123','AB123-','AB/123'])assert.equal(identificationIssue(value,'passport',specifications),'format');
});
test('foreign ID accepts spaces and internal hyphens up to 30 characters',()=>{
 for(const value of ['Ab 01','AB-12 34','B'.repeat(30)])assert.equal(identificationIssue(value,'foreign_id',specifications),null);
 for(const value of ['AB12','B'.repeat(31),'AB_123','AB/123','-AB123','AB123-'])assert.equal(identificationIssue(value,'foreign_id',specifications),'format');
});
test('identification type and number are required independently',()=>{
 assert.equal(identificationIssue('AB123','',specifications),'type');
 assert.equal(identificationIssue('','passport',specifications),'required');
});
