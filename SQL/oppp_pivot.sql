/*
 Navicat Premium Dump SQL

 Source Server         : JHCIS 192.168.1.25
 Source Server Type    : MySQL
 Source Server Version : 50645 (5.6.45)
 Source Host           : 192.168.1.25:3333
 Source Schema         : jhcisdb

 Target Server Type    : MySQL
 Target Server Version : 50645 (5.6.45)
 File Encoding         : 65001

 Date: 29/07/2025 17:11:50
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for oppp_pivot
-- ----------------------------
DROP TABLE IF EXISTS `oppp_pivot`;
CREATE TABLE `oppp_pivot`  (
  `hospcode` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `hospname` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `report_month` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `sent` tinyint(1) NULL DEFAULT 0,
  `upload_date` datetime NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`hospcode`, `report_month`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Compact;

SET FOREIGN_KEY_CHECKS = 1;
