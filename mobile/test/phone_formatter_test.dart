import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shop/phone_formatter.dart';

TextEditingValue value(String s, [int? cursor]) => TextEditingValue(
  text: s,
  selection: TextSelection.collapsed(offset: cursor ?? s.length),
);
void main() {
  final formatter = PhoneFormatter();
  test('pastes local and international formats', () {
    for (final input in [
      '7776218745',
      '+7 777 621 8745',
      '87776218745',
      '77776218745',
    ]) {
      final result = formatter.formatEditUpdate(value(''), value(input));
      expect(result.text, '777 621 8745');
      expect(result.selection.extentOffset, 12);
    }
  });
  test('typing, separator deletion, insertion and clearing', () {
    var current = value('');
    for (final digit in '7776218745'.split('')) {
      current = formatter.formatEditUpdate(
        current,
        value(current.text + digit),
      );
    }
    expect(current.text, '777 621 8745');
    expect(
      formatter.formatEditUpdate(value('777 621', 4), value('777621', 3)).text,
      '776 21',
    );
    expect(
      formatter.formatEditUpdate(value('777 621', 3), value('777621', 3)).text,
      '777 21',
    );
    final inserted = formatter.formatEditUpdate(
      value('777 621', 2),
      value('7797 621', 3),
    );
    expect(inserted.text, '779 762 1');
    expect(inserted.selection.extentOffset, 3);
    expect(formatter.formatEditUpdate(current, value('')).text, '');
  });
  test('rejects overflow rather than silently changing a complete number', () {
    final old = value('777 621 8745');
    expect(formatter.formatEditUpdate(old, value('777 621 874599')), old);
  });
}
