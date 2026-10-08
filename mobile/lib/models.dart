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
  const ShopCategory(this.id, this.name);
  factory ShopCategory.fromJson(Map<String, dynamic> json) =>
      ShopCategory((json['id'] as num).toInt(), json['name'] as String);
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
    final number = json['order_number'] as String;
    if (!RegExp(r'^DOS-[A-Z2-9]{8}$').hasMatch(number)) {
      throw const FormatException('Invalid order number');
    }
    return ShopSubmission(
      orderNumber: number,
      subtotalMinor: (num.parse(json['subtotal'].toString()) * 100).round(),
    );
  }
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
