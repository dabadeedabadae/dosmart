import 'package:flutter/services.dart';

/// National number only; the field displays a fixed +7 prefix separately.
class PhoneFormatter extends TextInputFormatter {
  static String digits(String text) => text.replaceAll(RegExp(r'\D'), '');

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    var number = digits(newValue.text);
    var caret = digits(
      newValue.text.substring(
        0,
        newValue.selection.extentOffset.clamp(0, newValue.text.length),
      ),
    ).length;
    final hasCountry =
        newValue.text.trimLeft().startsWith('+7') ||
        (number.length == 11 &&
            (number.startsWith('7') || number.startsWith('8')));
    if (hasCountry) {
      number = number.substring(1);
      caret = (caret - 1).clamp(0, number.length);
    }
    if (number.length > 10) return oldValue;
    // Deleting a separator should delete the adjacent digit, not restore
    // the space forever and leave the user stuck at a group boundary.
    if (number == digits(oldValue.text) &&
        newValue.text.length < oldValue.text.length &&
        oldValue.selection.isCollapsed &&
        number.isNotEmpty) {
      final backspace =
          newValue.selection.extentOffset < oldValue.selection.extentOffset;
      final index = backspace ? caret - 1 : caret;
      if (index >= 0 && index < number.length) {
        number = number.replaceRange(index, index + 1, '');
        if (backspace) caret--;
      }
    }
    final out = StringBuffer();
    var cursor = 0;
    for (var i = 0; i < number.length; i++) {
      if (i == 3 || i == 6) out.write(' ');
      out.write(number[i]);
      if (i < caret) cursor = out.length;
    }
    return TextEditingValue(
      text: out.toString(),
      selection: TextSelection.collapsed(offset: cursor),
    );
  }
}
