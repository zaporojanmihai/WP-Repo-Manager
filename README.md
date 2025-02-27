# WordPress Repository Manager

[![Github All Releases](https://img.shields.io/github/downloads/zaporojanmihai/WP-Repo-Manager/total.svg)]()

![image](https://github.com/user-attachments/assets/0e640b15-88df-44e0-bcbb-5b5a48d6e873)

A WordPress plugin that allows you to manage and automatically update private or public Git repositories directly from your WordPress admin dashboard.

## Features

- **Repository Management**: Add, edit, and delete Git repositories
- **Multiple Repository Types**: Support for both plugins and themes
- **Branch Selection**: Choose which branch to pull from for each repository
- **Automatic Updates**: Pull the latest changes from your repositories with a single click
- **Pull History**: Track all repository updates with detailed history and status indicators
- **Secure**: Safely store and manage repository credentials
- **User-Friendly Interface**: Clean, WordPress-native admin interface

## Installation

1. Download the plugin zip file
2. Go to WordPress admin > Plugins > Add New > Upload Plugin
3. Upload the zip file and activate the plugin
4. Navigate to Settings > WP Repo Manager to start managing your repositories

## Usage

### Adding a Repository

1. Navigate to Settings > WP Repo Manager
2. Click "Add New Repository"
3. Fill in the repository details:
   - Repository URL (HTTPS format)
   - Repository Type (Plugin/Theme)
   - Branch Name
   - Personal Access Token (for private repos)
4. Click "Add Repository"

### Updating Repositories

1. Go to the main plugin page
2. Find the repository you want to update
3. Click the "Pull" button next to the repository
4. View the pull status and history below the repository list

### Managing Repositories

- **Edit**: Click the edit icon to modify repository details
- **Delete**: Click the delete icon to remove a repository
- **Pull History**: View the complete history of all repository pulls with status indicators

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Git installed on the server
- Write permissions on the wp-content/plugins and wp-content/themes directories

## Security

- All admin actions are protected with nonces
- Repository operations are restricted to administrators only

## Development

Key features include:

- Clean separation of concerns (admin interface, repository handling, AJAX operations)
- WordPress coding standards compliance
- Secure credential handling
- User-friendly error handling and status feedback
- Comprehensive pull history tracking

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL v2 or later.

## Author

@author [Mihai Zaporojan](https://github.com/zaporojanmihai)

## Changelog

### 1.0.0
- Initial release
- Repository management functionality
- Pull history tracking
- Secure credential storage
- WordPress admin interface
