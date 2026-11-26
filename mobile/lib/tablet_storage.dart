import 'dart:convert';
import 'dart:io';

import 'package:path_provider/path_provider.dart';

import 'models.dart';

/// Где планшет хранит привязку к учреждению. Сеансы осуждённых не сохраняются.
abstract class TabletStorage {
  Future<TabletInfo?> load();
  Future<void> save(TabletInfo? info);
}

class MemoryTabletStorage implements TabletStorage {
  TabletInfo? value;
  MemoryTabletStorage([this.value]);
  @override
  Future<TabletInfo?> load() async => value;
  @override
  Future<void> save(TabletInfo? info) async => value = info;
}

class FileTabletStorage implements TabletStorage {
  Future<File> _file() async =>
      File('${(await getApplicationSupportDirectory()).path}/tablet.json');

  @override
  Future<TabletInfo?> load() async {
    try {
      final file = await _file();
      if (!await file.exists()) return null;
      return TabletInfo.fromJson(jsonDecode(await file.readAsString()));
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> save(TabletInfo? info) async {
    final file = await _file();
    if (info == null) {
      if (await file.exists()) await file.delete();
      return;
    }
    await file.parent.create(recursive: true);
    await file.writeAsString(jsonEncode(info.toJson()), flush: true);
  }
}
