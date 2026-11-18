enum PaymentMethodType { card, mobileMoney }

extension PaymentMethodTypeX on PaymentMethodType {
  String get storageValue => name;

  static PaymentMethodType fromStorage(String value) {
    return PaymentMethodType.values.firstWhere(
      (type) => type.storageValue == value,
      orElse: () => PaymentMethodType.card,
    );
  }
}

class PaymentMethod {
  const PaymentMethod({
    required this.id,
    required this.type,
    required this.label,
    required this.detail,
    this.expiry,
    this.isDefault = false,
  });

  final String id;
  final PaymentMethodType type;
  final String label;
  final String detail;
  final String? expiry;
  final bool isDefault;

  PaymentMethod copyWith({
    String? id,
    PaymentMethodType? type,
    String? label,
    String? detail,
    String? expiry,
    bool? isDefault,
  }) {
    return PaymentMethod(
      id: id ?? this.id,
      type: type ?? this.type,
      label: label ?? this.label,
      detail: detail ?? this.detail,
      expiry: expiry ?? this.expiry,
      isDefault: isDefault ?? this.isDefault,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type.storageValue,
      'label': label,
      'detail': detail,
      'expiry': expiry,
      'isDefault': isDefault,
    };
  }

  static PaymentMethod fromJson(Map<String, dynamic> json) {
    return PaymentMethod(
      id: json['id']?.toString() ?? '',
      type: PaymentMethodTypeX.fromStorage(json['type']?.toString() ?? ''),
      label: json['label']?.toString() ?? '',
      detail: json['detail']?.toString() ?? '',
      expiry: json['expiry']?.toString(),
      isDefault: json['isDefault'] == true,
    );
  }
}
