class ShopProduct {
  final int id;
  final int? categoryId;
  final String name;
  final int priceMinor;
  final String? unit;
  final String? imageUrl;

  const ShopProduct({
    required this.id,
    this.categoryId,
    required this.name,
    required this.priceMinor,
    this.unit,
    this.imageUrl,
  });

  factory ShopProduct.fromJson(Map<String, dynamic> json) => ShopProduct(
    id: (json['id'] as num).toInt(),
    categoryId: (json['category_id'] as num?)?.toInt(),
    name: json['name'] as String,
    priceMinor: (num.parse(json['price'].toString()) * 100).round(),
    unit: json['unit'] as String?,
    imageUrl: json['image_url'] as String?,
  );
}

class ShopCategory {
  final int id;
  final String name;
  final List<int> categoryIds;
  const ShopCategory(this.id, this.name, [this.categoryIds = const []]);
  bool includes(int? categoryId) =>
      categoryId == id || categoryIds.contains(categoryId);
  factory ShopCategory.fromJson(Map<String, dynamic> json) => ShopCategory(
    (json['id'] as num).toInt(),
    json['name'] as String,
    ((json['category_ids'] as List?) ?? const [])
        .map((id) => (id as num).toInt())
        .toList(),
  );

  static List<ShopCategory> fromTree(List<dynamic> rows, [String parent = '']) {
    final result = <ShopCategory>[];
    for (final row in rows) {
      final node = ShopCategory.fromJson(row as Map<String, dynamic>);
      final children = fromTree(
        (row['children'] as List?) ?? const [],
        parent.isEmpty ? node.name : '$parent / ${node.name}',
      );
      result.add(
        ShopCategory(
          node.id,
          parent.isEmpty ? node.name : '$parent / ${node.name}',
          {
            ...node.categoryIds,
            node.id,
            ...children.expand((c) => c.categoryIds),
          }.toList(),
        ),
      );
      result.addAll(children);
    }
    return result;
  }
}

class ShopCartLine {
  final ShopProduct product;
  final int quantity;
  const ShopCartLine(this.product, this.quantity);
  int get totalMinor => product.priceMinor * quantity;
  Map<String, int> toJson() => {'product_id': product.id, 'quantity': quantity};
}

class ShopSubmission {
  final String orderNumber;
  final int subtotalMinor;
  const ShopSubmission({
    required this.orderNumber,
    required this.subtotalMinor,
  });
  factory ShopSubmission.fromJson(Map<String, dynamic> json) {
    final number =
        (json['display_order_number'] ?? json['order_number']) as String;
    if (!RegExp(
      r'^(DOS-[A-Z2-9]{8}|[0-9]{2}(0[1-9]|1[0-2])[0-9]{3}|[0-9]{2}-[0-9]{2}-(0[1-9]|1[0-2])-[0-9]{3,4})$',
    ).hasMatch(number)) {
      throw const FormatException('Invalid order number');
    }
    return ShopSubmission(
      orderNumber: number,
      subtotalMinor: (num.parse(json['subtotal'].toString()) * 100).round(),
    );
  }
}

class WalletProfile {
  final String fullName, institution, condition;
  final String? squad, authMethod, familyCode, recoveryMethod, recoveryQuestion;
  final int balanceMinor, deliveryMinor, monthlySpentMinor, limitCarriedMinor;
  final int? monthlyRemainingMinor, monthlyLimitMinor, parcelsAvailable;
  final int? conditionNormMrp;
  final bool familyCodeActive, birthDateSet, reviewed;

  const WalletProfile({
    required this.fullName,
    required this.balanceMinor,
    required this.deliveryMinor,
    this.institution = '',
    this.condition = '',
    this.conditionNormMrp,
    this.squad,
    this.authMethod,
    this.familyCode,
    this.monthlySpentMinor = 0,
    this.limitCarriedMinor = 0,
    this.monthlyLimitMinor,
    this.monthlyRemainingMinor,
    this.parcelsAvailable,
    this.familyCodeActive = false,
    this.recoveryMethod,
    this.recoveryQuestion,
    this.birthDateSet = false,
    this.reviewed = true,
  });

  factory WalletProfile.fromJson(Map<String, dynamic> json) => WalletProfile(
    fullName: json['full_name'] as String,
    institution: json['institution'] as String? ?? '',
    condition: json['condition'] as String? ?? '',
    conditionNormMrp: (json['condition_norm_mrp'] as num?)?.toInt(),
    squad: json['squad'] as String?,
    authMethod: json['auth_method'] as String?,
    familyCode: json['family_code'] as String?,
    balanceMinor: (json['balance_minor'] as num?)?.toInt() ?? 0,
    deliveryMinor: (json['delivery_minor'] as num?)?.toInt() ?? 0,
    monthlySpentMinor: json['monthly_spent_minor'] as int? ?? 0,
    limitCarriedMinor: json['limit_carried_minor'] as int? ?? 0,
    monthlyLimitMinor: json['monthly_limit_minor'] as int?,
    monthlyRemainingMinor: json['monthly_remaining_minor'] as int?,
    parcelsAvailable: json['parcels_available'] as int?,
    familyCodeActive: json['family_code_active'] as bool? ?? false,
    recoveryMethod: json['recovery_method'] as String?,
    recoveryQuestion: json['recovery_question'] as String?,
    birthDateSet: json['birth_date_set'] as bool? ?? false,
    reviewed: json['reviewed'] as bool? ?? true,
  );
}

/// Привязка планшета к учреждению (код из админки).
class TabletInfo {
  final String token;
  final int institutionId;
  final String institutionName;
  const TabletInfo(this.token, this.institutionId, this.institutionName);
  Map<String, dynamic> toJson() => {
    'token': token,
    'institution_id': institutionId,
    'institution_name': institutionName,
  };
  static TabletInfo? fromJson(Object? json) {
    if (json is! Map ||
        json['token'] is! String ||
        json['institution_id'] is! num ||
        json['institution_name'] is! String) {
      return null;
    }
    return TabletInfo(
      json['token'] as String,
      (json['institution_id'] as num).toInt(),
      json['institution_name'] as String,
    );
  }
}

/// Условия содержания — как в карточке в админке.
const prisonerConditions = {
  'ordinary': 'Обычные',
  'eased': 'Облегчённые',
  'strict': 'Строгие',
};

const securityQuestions = {
  'grandmother': 'Имя вашей бабушки по маме',
  'birth_place': 'Город или село, где вы родились',
  'first_school': 'Номер или название вашей первой школы',
};

class WalletOperation {
  final String kind, title, at;
  final String? status;
  final int amountMinor;
  const WalletOperation(
    this.kind,
    this.title,
    this.amountMinor,
    this.at, [
    this.status,
  ]);
  factory WalletOperation.fromJson(Map<String, dynamic> json) =>
      WalletOperation(
        json['kind'] as String,
        json['title'] as String,
        json['amount_minor'] as int,
        json['at'] as String,
        json['status'] as String?,
      );
}

/// Успешный вход или регистрация.
class WalletLogin {
  final String token;
  final WalletProfile profile;
  const WalletLogin(this.token, this.profile);
}

class WalletReceipt {
  final String orderNumber;
  final int totalMinor;
  const WalletReceipt(this.orderNumber, this.totalMinor);
}

class ShopException implements Exception {
  final String message;
  final int? status;
  const ShopException(this.message, [this.status]);
  @override
  String toString() => message;
}

class ShopInstitution {
  final int id;
  final String name;
  final String city;
  const ShopInstitution(this.id, this.name, this.city);
  String get label => city.isEmpty ? name : '$name — $city';
  factory ShopInstitution.fromJson(Map<String, dynamic> json) =>
      ShopInstitution(
        (json['id'] as num).toInt(),
        json['name'] as String,
        json['city'] as String? ?? '',
      );
}
