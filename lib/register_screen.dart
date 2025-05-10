import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:async';
import 'dart:developer' as developer;

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  // Constants
  static const String baseUrl = 'http://192.168.110.73/voting';
  static const Duration apiTimeout = Duration(seconds: 10);
  static const double imageSizeLimitMB = 2.0;

  // Form and controllers
  final _formKey = GlobalKey<FormState>();
  final _firstNameController = TextEditingController();
  final _middleNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _contactNumberController = TextEditingController();

  // State variables
  String? _registrationType;
  String? _selectedPosition;
  File? _candidateImage;
  final ImagePicker _picker = ImagePicker();
  bool _isSubmitting = false;

  // Position options
  final List<String> _positions = [
    'President',
    'Vice-President',
    'Secretary',
    'Treasurer',
    'Auditor',
    'Business Manager',
    'Press Relation Officer',
  ];

  Future<void> _pickImage() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 800, // Limit image size
        imageQuality: 85, // Compress image
      );

      if (image != null) {
        final file = File(image.path);
        final fileSize = await file.length() / (1024 * 1024); // MB

        if (fileSize > imageSizeLimitMB) {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                'Image must be less than $imageSizeLimitMB MB. Current size: ${fileSize.toStringAsFixed(2)} MB',
              ),
            ),
          );
          return;
        }

        setState(() {
          _candidateImage = file;
        });
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to pick image: ${e.toString()}')),
      );
    }
  }

  Future<void> _checkExistingUser() async {
    final response = await http
        .post(
          Uri.parse('$baseUrl/check_registration.php'),
          headers: {'Content-Type': 'application/json'},
          body: jsonEncode({'contactNumber': _contactNumberController.text}),
        )
        .timeout(apiTimeout);

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      if (data['exists'] == true && data['type'] == 'candidate') {
        throw Exception(
          'This contact number is already registered as a candidate',
        );
      }
      // Allow registration if user is only a voter
    } else {
      throw Exception(
        'Failed to check registration status: ${response.statusCode}',
      );
    }
  }

  Future<void> _submitForm() async {
    if (!_formKey.currentState!.validate()) return;

    // Validate registration type
    if (_registrationType == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select registration type')),
      );
      return;
    }

    // Validate candidate-specific fields
    if (_registrationType == 'candidate') {
      if (_selectedPosition == null) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Please select position')));
        return;
      }

      if (_candidateImage == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please upload your photo')),
        );
        return;
      }
    }

    setState(() => _isSubmitting = true);

    // Declare response variable here so it's accessible in catch blocks
    http.Response? response;

    try {
      await _checkExistingUser();

      // Prepare registration data
      final userData = {
        'firstName': _firstNameController.text.trim(),
        'middleName': _middleNameController.text.trim(),
        'lastName': _lastNameController.text.trim(),
        'contactNumber': _contactNumberController.text.trim(),
        'type': _registrationType,
        if (_registrationType == 'candidate') 'position': _selectedPosition,
      };

      // Handle image if candidate
      if (_registrationType == 'candidate' && _candidateImage != null) {
        final bytes = await _candidateImage!.readAsBytes();
        userData['image'] = 'data:image/jpeg;base64,${base64Encode(bytes)}';
      }

      // Submit registration
      response = await http
          .post(
            Uri.parse('$baseUrl/register_user.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode(userData),
          )
          .timeout(apiTimeout);

      developer.log(
        'Registration response: ${response.statusCode} - ${response.body}',
      );

      // Handle empty response
      if (response.body.isEmpty) {
        throw FormatException('Server returned empty response');
      }

      // Parse JSON
      final responseData = jsonDecode(response.body) as Map<String, dynamic>;

      // Check for success
      if (response.statusCode >= 200 && response.statusCode < 300) {
        if (responseData['success'] == true) {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Registration successful!')),
          );
          Navigator.pop(context, {
            'voterId': responseData['voterId'],
            'type': _registrationType,
            if (_registrationType == 'candidate') ...{
              'candidateId': responseData['candidateId'],
              'position': _selectedPosition,
            },
          });
        } else {
          throw Exception(
            responseData['error'] ??
                responseData['message'] ??
                'Registration failed',
          );
        }
      } else {
        throw Exception(
          responseData['error'] ??
              responseData['message'] ??
              'Server responded with status: ${response.statusCode}',
        );
      }
    } on FormatException catch (e) { 
      // Now response is accessible here
      developer.log(
        'Raw response that failed to parse: ${response?.body ?? "No response"}',
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Invalid server response: ${e.message}')),
      );
    } on TimeoutException {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Request timed out. Please try again.')),
      );
    } on SocketException {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Network connection failed. Please check your internet.',
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_formatErrorMessage(e.toString()))),
      );
      developer.log('Registration error', error: e);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  String _formatErrorMessage(String error) {
    return error
        .replaceAll(RegExp(r'^Exception: '), '')
        .replaceAll(RegExp(r'\.$'), '');
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _middleNameController.dispose();
    _lastNameController.dispose();
    _contactNumberController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Registration')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Personal Information Fields
              _buildNameField(
                controller: _firstNameController,
                label: 'First Name',
                isRequired: true,
              ),
              const SizedBox(height: 16),
              _buildNameField(
                controller: _middleNameController,
                label: 'Middle Name (Optional)',
                isRequired: false,
              ),
              const SizedBox(height: 16),
              _buildNameField(
                controller: _lastNameController,
                label: 'Last Name',
                isRequired: true,
              ),
              const SizedBox(height: 16),
              _buildContactNumberField(),
              const SizedBox(height: 24),

              // Registration Type Selection
              _buildSectionTitle('Register as:'),
              const SizedBox(height: 8),
              _buildRegistrationTypeSelector(),
              const SizedBox(height: 24),

              // Candidate-Specific Fields (Conditional)
              if (_registrationType == 'candidate') ...[
                _buildCandidateFields(),
              ],

              // Submit Button
              ElevatedButton(
                onPressed: _isSubmitting ? null : _submitForm,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child:
                    _isSubmitting
                        ? const CircularProgressIndicator()
                        : const Text('Submit Registration'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNameField({
    required TextEditingController controller,
    required String label,
    required bool isRequired,
  }) {
    return TextFormField(
      controller: controller,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
      validator:
          isRequired
              ? (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'This field is required';
                }
                return null;
              }
              : null,
    );
  }

  Widget _buildContactNumberField() {
    return TextFormField(
      controller: _contactNumberController,
      decoration: const InputDecoration(
        labelText: 'Contact Number (+639XXXXXXXXX)',
        border: OutlineInputBorder(),
        prefixText: '+63',
      ),
      keyboardType: TextInputType.phone,
      validator: (value) {
        if (value == null || value.isEmpty) {
          return 'Please enter your contact number';
        }
        if (!RegExp(r'^9\d{9}$').hasMatch(value)) {
          return 'Please enter a valid 10-digit number (9XXXXXXXXX)';
        }
        return null;
      },
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
    );
  }

  Widget _buildRegistrationTypeSelector() {
    return Row(
      children: [
        Expanded(
          child: ChoiceChip(
            label: const Text('Voter'),
            selected: _registrationType == 'voter',
            onSelected: (selected) {
              setState(() {
                _registrationType = selected ? 'voter' : null;
                if (!selected) {
                  _selectedPosition = null;
                  _candidateImage = null;
                }
              });
            },
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: ChoiceChip(
            label: const Text('Candidate'),
            selected: _registrationType == 'candidate',
            onSelected: (selected) {
              setState(() {
                _registrationType = selected ? 'candidate' : null;
              });
            },
          ),
        ),
      ],
    );
  }

  Widget _buildCandidateFields() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _buildSectionTitle('Position:'),
        const SizedBox(height: 8),
        DropdownButtonFormField<String>(
          value: _selectedPosition,
          decoration: const InputDecoration(
            border: OutlineInputBorder(),
            contentPadding: EdgeInsets.symmetric(horizontal: 12),
          ),
          items:
              _positions.map((position) {
                return DropdownMenuItem(value: position, child: Text(position));
              }).toList(),
          onChanged: (value) {
            setState(() => _selectedPosition = value);
          },
          validator: (value) {
            if (_registrationType == 'candidate' && value == null) {
              return 'Please select a position';
            }
            return null;
          },
        ),
        const SizedBox(height: 16),
        _buildSectionTitle('Upload Your Photo:'),
        const SizedBox(height: 8),
        _buildImageUploader(),
        const SizedBox(height: 16),
      ],
    );
  }

  Widget _buildImageUploader() {
    return GestureDetector(
      onTap: _pickImage,
      child: Container(
        height: 150,
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey),
          borderRadius: BorderRadius.circular(8),
        ),
        child:
            _candidateImage == null
                ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.camera_alt, size: 40),
                      Text('Tap to upload photo'),
                    ],
                  ),
                )
                : Stack(
                  children: [
                    Image.file(_candidateImage!, fit: BoxFit.cover),
                    Positioned(
                      top: 8,
                      right: 8,
                      child: IconButton(
                        icon: const Icon(Icons.close, color: Colors.white),
                        onPressed: () {
                          setState(() => _candidateImage = null);
                        },
                      ),
                    ),
                  ],
                ),
      ),
    );
  }
}
